<?php

namespace Resque;

use Resque\Backend\RedisBackend;
use Resque\Backend\BackendInterface;

/**
 * Base Resque class.
 *
 * @package     Resque
 * @author      Chris Boulton <chris@bigcommerce.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class Resque
{
    public $backend = null;
    public $backendConfig = null;
    public $stat;
    public $event;

    public function setBackend(BackendInterface $backend)
    {
        $this->backend = $backend;
    }

    public function setBackendConfig(array $config)
    {
        $this->backendConfig = $config;
    }

    public function setPrefix($prefix)
    {
        $this->getBackend()->setPrefix($prefix);
    }

    public function getBackend()
    {
        return $this->backend ?: $this->backend = new RedisBackend($this->backendConfig ?: array(
            'server' => 'localhost:6379',
        ));
    }

    /**
     * Will close connection to the backend before forking.
     *
     * @return int               Return vars as per pcntl_fork()
     * @throws \RuntimeException
     */
    public function fork()
    {
        if (!function_exists('pcntl_fork')) {
            return -1;
        }

        // $this->getBackend()->close();
        $this->backend = null;

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }

    /**
     * Push a job to the end of a specific queue. If the queue does not
     * exist, then create it as well.
     *
     * @param string $queue The name of the queue to add the job to.
     * @param array  $item  Job description as an array to be JSON encoded.
     */
    public function push($queue, $item)
    {
        $this->getBackend()->sadd('queues', $queue);
        $this->getBackend()->rpush('queue:' . $queue, json_encode($item));
    }

    /**
     * Pop an item off the end of the specified queue, decode it and
     * return it.
     *
     * @param  string $queue The name of the queue to fetch an item from.
     * @return array  Decoded item from the queue.
     */
    public function pop($queue)
    {
        $item = $this->getBackend()->lpop('queue:' . $queue);
        if (!$item) {
            return null;
        }

        return json_decode($item, true);
    }

    /**
     * Return the size (number of pending jobs) of the specified queue.
     *
     * @param $queue string name of the queue to be checked for pending jobs
     *
     * @return int The size of the queue.
     */
    public function size($queue)
    {
        return $this->getBackend()->llen('queue:' . $queue);
    }

    /**
     * Create a new job and save it to the specified queue.
     *
     * @param string  $queue       The name of the queue to place the job in.
     * @param string  $class       The name of the class that contains the code to execute the job.
     * @param array   $args        Any optional arguments that should be passed when the job is executed.
     * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
     *
     * @return string
     */
    public function enqueue($queue, $class, $args = array(), $trackStatus = false)
    {
        $job = new Job($this, $queue, $class, $args);
        $result = $job->create($queue, $class, $args, $trackStatus);

        if ($result) {
            $this->getEvent()->trigger('afterEnqueue', array(
                'class' => $class,
                'args'  => $args,
                'queue' => $queue,
            ));
        }

        return $result;
    }

    /**
     * Get an array of all known queues.
     *
     * @return array Array of queues.
     */
    public function queues()
    {
        $queues = $this->getBackend()->smembers('queues');
        if (!is_array($queues)) {
            $queues = array();
        }

        return $queues;
    }

    public function workers()
    {
        $workers = $this->getBackend()->smembers('workers');
        if (!is_array($workers)) {
            $workers = array();
        }

        $instances = array();
        foreach ($workers as $workerId) {
            $instances[] = $this->worker($workerId);
        }

        return $instances;
    }

    public function worker($workerId)
    {
        $exists = (bool) $this->getBackend()->sismember('workers', $workerId);;
        if (!$exists || false === strpos($workerId, ":")) {
            return false;
        }

        list(,,$queues) = explode(':', $workerId, 3);
        $queues = explode(',', $queues);

        $worker = new Worker($this, $queues);
        $worker->setId($workerId);

        return $worker;
    }

    /**
     * Register this worker in the backend.
     */
    public function registerWorker(Worker $worker)
    {
        $this->getBackend()->sadd('workers', (string) $worker);
        $this->getBackend()->set('worker:' . (string) $worker . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
    }

    /**
     * Unregister this worker in getBackend. (shutdown etc)
     */
    public function unregisterWorker(Worker $worker)
    {
        if (is_object($worker->currentJob)) {
            $worker->currentJob->fail(new Job\DirtyExitException);
        }

        $id = (string) $worker;
        $this->getBackend()->srem('workers', $id);
        $this->getBackend()->del('worker:' . $id);
        $this->getBackend()->del('worker:' . $id . ':started');
        $this->getStat()->clear('processed:' . $id);
        $this->getStat()->clear('failed:' . $id);
    }

    public function getStat()
    {
        if (! $this->stat) {
            $this->stat = new Stat($this->getBackend());
        }

        return $this->stat;
    }

    public function getEvent()
    {
        if (! $this->event) {
            $this->event = new Event;
        }

        return $this->event;
    }

    public function reserve($queues = false)
    {
        $queues = $queues ?: $this->queues();
        if (!is_array($queues)) {
            return null;
        }
        foreach ($queues as $queue) {
            // $this->log('Checking ' . $queue);
            $job = $this->reserveJob($queue);
            if ($job) {
                // $this->log('Found job on ' . $queue);
                return $job;
            }
        }

        return false;
    }

    /**
     * Find the next available job from the specified queue and return an
     * instance of Job for it.
     *
     * @param  string      $queue The name of the queue to check for a job in.
     * @return null|object Null when there aren't any waiting jobs, instance of Job when a job was found.
     */
    public function reserveJob($queue = '*')
    {
        $payload = $this->pop($queue);

        if (!is_array($payload)) {
            return false;
        }

        return new Job($this, $queue, $payload);
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param  string  $workerId ID of the worker.
     * @return boolean True if the worker exists, false if not.
     */
    public function workerExists($workerId)
    {
        return (bool) $this->getBackend()->sismember('workers', $workerId);
    }

    /**
     * Given a worker ID, find it and return an instantiated worker class for it.
     *
     * @param  string $workerId The ID of the worker.
     * @return Worker Instance of the worker. False if the worker does not exist.
     */
    public function findWorker($workerId)
    {
        if (!$this->workerExists($workerId) || false === strpos($workerId, ":")) {
            return false;
        }

        list(,,$queues) = explode(':', $workerId, 3);
        $queues = explode(',', $queues);

        $worker = new Worker($this, $queues);
        $worker->setId($workerId);

        return $worker;
    }
}
