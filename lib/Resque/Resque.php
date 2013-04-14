<?php

namespace Resque;

use Resque\Backend\RedisBackend;

/**
 * Base Resque class.
 *
 * @package     Resque
 * @author      Chris Boulton <chris@bigcommerce.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class Resque
{
    public static $backend = null;

    protected static $backendConfig = null;

    public static function setBackendConfig(array $config)
    {
        self::$backendConfig = $config;
    }

    public static function setPrefix($prefix)
    {
        self::getBackend()->setPrefix($prefix);
    }

    public static function getBackend()
    {
        return self::$backend ?: self::$backend = new RedisBackend(self::$backendConfig ?: 'localhost:6379');
    }

    /**
     * Will close connection to the backend before forking.
     *
     * @return int Return vars as per pcntl_fork()
     * @throws \RuntimeException
     */
    public static function fork()
    {
        if (!function_exists('pcntl_fork')) {
            return -1;
        }

        // self::getBackend()->close();
        self::$backend = null;

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
    public static function push($queue, $item)
    {
        self::getBackend()->sadd('queues', $queue);
        self::getBackend()->rpush('queue:' . $queue, json_encode($item));
    }

    /**
     * Pop an item off the end of the specified queue, decode it and
     * return it.
     *
     * @param  string $queue The name of the queue to fetch an item from.
     * @return array  Decoded item from the queue.
     */
    public static function pop($queue)
    {
        $item = self::getBackend()->lpop('queue:' . $queue);
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
    public static function size($queue)
    {
        return self::getBackend()->llen('queue:' . $queue);
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
    public static function enqueue($queue, $class, $args = array(), $trackStatus = false)
    {
        $result = Job::create($queue, $class, $args, $trackStatus);
        if ($result) {
            Event::trigger('afterEnqueue', array(
                'class' => $class,
                'args'  => $args,
                'queue' => $queue,
            ));
        }

        return $result;
    }

    /**
     * Reserve and return the next available job in the specified queue.
     *
     * @param  string $queue Queue to fetch next available job from.
     * @return Job    Instance of Job to be processed, false if none or error.
     */
    public static function reserve($queue)
    {
        return Job::reserve($queue);
    }

    /**
     * Get an array of all known queues.
     *
     * @return array Array of queues.
     */
    public static function queues()
    {
        $queues = self::getBackend()->smembers('queues');
        if (!is_array($queues)) {
            $queues = array();
        }

        return $queues;
    }
}
