<?php

namespace Resque;

use Resque\Job\Strategy\Fork;
use Resque\Job\Strategy\InProcess;
use Resque\Job\Strategy\StrategyInterface;

/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @package		Resque/Worker
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Worker
{
    const LOG_DEBUG = 100;
    const LOG_INFO = 200;
    const LOG_NOTICE = 250;
    const LOG_WARNING = 400;
    const LOG_ERROR = 400;
    const LOG_CRITICAL = 500;
    const LOG_ALERT = 550;

    /**
     * @var int Current log level of this worker.
     */
    public $logLevel = 0;

    /**
     * @var array Array of all associated queues for this worker.
     */
    private $queues = array();

    /**
     * @var string The hostname of this worker.
     */
    private $hostname;

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    private $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    private $paused = false;

    /**
     * @var string String identifying this worker.
     */
    private $id;

    /**
     * @var Job Current job, if any, being processed by this worker.
     */
    private $currentJob = null;

    /**
     * @var @jobStrategy to use for job execution.
     */
    private $jobStrategy;

    /**
     * @var integer processed job count
     */
    private $processed = 0;

    private $interval = 5;

    /**
     * Return all workers known to Resque as instantiated instances.
     * @return array
     */
    public static function all()
    {
        $workers = Resque::getBackend()->smembers('workers');
        if (!is_array($workers)) {
            $workers = array();
        }

        $instances = array();
        foreach ($workers as $workerId) {
            $instances[] = self::find($workerId);
        }

        return $instances;
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param  string  $workerId ID of the worker.
     * @return boolean True if the worker exists, false if not.
     */
    public static function exists($workerId)
    {
        return (bool) Resque::getBackend()->sismember('workers', $workerId);
    }

    /**
     * Given a worker ID, find it and return an instantiated worker class for it.
     *
     * @param  string $workerId The ID of the worker.
     * @return Worker Instance of the worker. False if the worker does not exist.
     */
    public static function find($workerId)
    {
        if (!self::exists($workerId) || false === strpos($workerId, ":")) {
            return false;
        }

        list(,,$queues) = explode(':', $workerId, 3);
        $queues = explode(',', $queues);
        $worker = new self($queues);
        $worker->setId($workerId);

        return $worker;
    }

    /**
     * Set the ID of this worker to a given ID string.
     *
     * @param string $workerId ID for the worker.
     */
    public function setId($workerId)
    {
        $this->id = $workerId;
    }

    /**
     * Logging level to use
     *
     * @param string $level String representing the end-half of a LOG_ const
     */
    public function setLogLevel($level)
    {
        $this->logLevel = constant('\Resque\Worker::LOG_' . strtoupper($level));
    }

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     *
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param string|array $queues String with a single queue name, array with multiple.
     */
    public function __construct($queues = array())
    {
        if (!is_array($queues)) {
            $queues = array($queues);
        }

        $this->queues = $queues;
        if (function_exists('gethostname')) {
            $hostname = gethostname();
        } else {
            $hostname = php_uname('n');
        }
        $this->hostname = $hostname;
        $this->id = $this->hostname . ':' . getmypid() . ':' . implode(',', $this->queues);
    }

    /**
     * Set the JobStrategy used to separate the job execution context from the worker
     *
     * @param StrategyInterface $jobStrategy
     */
    public function setJobStrategy(StrategyInterface $jobStrategy)
    {
        $this->jobStrategy = $jobStrategy;
        $this->jobStrategy->setWorker($this);
    }

    /**
     * The primary loop for a worker which when called on an instance starts
     * the worker's life cycle.
     *
     * Queues are checked every $interval (seconds) for new jobs.
     *
     * @param int $interval How often to check for new jobs across the queues.
     */
    public function work($interval = 5)
    {
        if (! is_null($interval)) {
            $this->interval = $interval;
        }

        $this->updateProcLine('Starting');
        $this->startup();

        while (true) {
            if ($this->shutdown) {
                break;
            }

            // Attempt to find and reserve a job
            $job = false;
            if (!$this->paused) {
                $job = $this->reserve();
            }

            if (!$job) {
                // For an interval of 0, break now - helps with unit testing etc
                if ($this->interval == 0) {
                    break;
                }
                // If no job was found, we sleep for $interval before continuing and checking again
                $this->log('Sleeping for ' . $this->interval);
                if ($this->paused) {
                    $this->updateProcLine('Paused');
                } else {
                    $this->updateProcLine('Waiting for ' . implode(',', $this->queues));
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $this->log('Received ' . $job);
            Event::trigger('beforeFork', $job);
            $this->workingOn($job);

            $this->getJobStrategy()->perform($job);

            $this->processed++;

            $this->doneWorking();
        }

        $this->unregisterWorker();
    }

    public function getJobStrategy()
    {
        if (! $this->jobStrategy) {
            if (function_exists('pcntl_fork')) {
                $this->setJobStrategy(new Fork);
            } else {
                $this->setJobStrategy(new InProcess);
            }
        }

        return $this->jobStrategy;
    }

    /**
     * Process a single job.
     *
     * @param Job $job The job to be processed.
     */
    public function perform(Job $job)
    {
        try {
            Event::trigger('afterFork', $job);
            $job->perform();
        } catch (\Exception $e) {
            $this->log($job . ' failed: ' . $e->getMessage());
            $job->fail($e);

            return;
        }

        $job->updateStatus(Job\Status::STATUS_COMPLETE);
        $this->log('Done ' . $job);
    }

    /**
     * Attempt to find a job from the top of one of the queues for this worker.
     *
     * @return object|boolean Instance of Job if a job is found, false if not.
     */
    public function reserve()
    {
        $queues = $this->queues();
        if (!is_array($queues)) {
            return null;
        }
        foreach ($queues as $queue) {
            $this->log('Checking ' . $queue);
            $job = Job::reserve($queue);
            if ($job) {
                $this->log('Found job on ' . $queue);

                return $job;
            }
        }

        return false;
    }

    /**
     * Return an array containing all of the queues that this worker should use
     * when searching for jobs.
     *
     * If * is found in the list of queues, every queue will be searched in
     * alphabetic order. (@see $fetch)
     *
     * @param boolean $fetch If true, and the queue is set to *, will fetch
     * all queue names from the backend.
     * @return array Array of associated queues.
     */
    public function queues($fetch = true)
    {
        if (!in_array('*', $this->queues) || $fetch == false) {
            return $this->queues;
        }

        $queues = Resque::queues();
        sort($queues);

        return $queues;
    }

    /**
     * Perform necessary actions to start a worker.
     */
    private function startup()
    {
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        Event::trigger('beforeFirstFork', $this);
        $this->registerWorker();
    }

    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title.
     */
    public function updateProcLine($status)
    {
        if (function_exists('setproctitle')) {
            setproctitle('resque: ' . $status);
        }
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers()
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        declare(ticks = 1);
        pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
        pcntl_signal(SIGINT, array($this, 'shutDownNow'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR1, array($this, 'killChild'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
        pcntl_signal(SIGPIPE, array($this, 'reestablishBackendConnection'));
        $this->log('Registered signals');
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        $this->log('USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        $this->log('CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Signal handler for SIGPIPE, in the event the backend connection has gone away.
     * Attempts to reconnect to the backend, or raises an Exception.
     */
    public function reestablishBackendConnection()
    {
        $this->log('SIGPIPE received; attempting to reconnect');
        Resque::getBackend()->establishConnection();
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        $this->shutdown = true;
        $this->log('Exiting...');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        $this->shutdown();
        $this->killChild();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChild()
    {
        if ($this->jobStrategy) {
            $this->jobStrategy->shutdown();
        }
    }

    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from the backend.
     *
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in the backend.
     */
    public function pruneDeadWorkers()
    {
        $workerPids = $this->workerPids();
        $workers = self::all();
        foreach ($workers as $worker) {
          if (is_object($worker)) {
              list($host, $pid) = explode(':', (string) $worker, 2);
              if ($host != $this->hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
                  continue;
              }
              $this->log('Pruning dead worker: ' . (string) $worker);
              $worker->unregisterWorker();
          }
        }
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array Array of Resque worker process IDs.
     */
    public function workerPids()
    {
        $pids = array();
        exec('ps -A -o pid,command | grep [r]esque', $cmdOutput);
        foreach ($cmdOutput as $line) {
            list($pids[],) = explode(' ', trim($line), 2);
        }

        return $pids;
    }

    /**
     * Register this worker in the backend.
     */
    public function registerWorker()
    {
        Resque::getBackend()->sadd('workers', (string) $this);
        Resque::getBackend()->set('worker:' . (string) $this . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
    }

    /**
     * Unregister this worker in getBackend. (shutdown etc)
     */
    public function unregisterWorker()
    {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new Job\DirtyExitException);
        }

        $id = (string) $this;
        Resque::getBackend()->srem('workers', $id);
        Resque::getBackend()->del('worker:' . $id);
        Resque::getBackend()->del('worker:' . $id . ':started');
        Stat::clear('processed:' . $id);
        Stat::clear('failed:' . $id);
    }

    /**
     * Tell the backend which job we're currently working on.
     *
     * @param Job $job Job instance containing the job we're working on.
     */
    public function workingOn(Job $job)
    {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(Job\Status::STATUS_RUNNING);
        $data = json_encode(array(
            'queue' => $job->queue,
            'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
            'payload' => $job->payload
        ));
        Resque::getBackend()->set('worker:' . $job->worker, $data);
    }

    /**
     * Notify the backend that we've finished working on a job, clearing the working
     * state and incrementing the job stats.
     */
    public function doneWorking()
    {
        $this->currentJob = null;
        Stat::incr('processed');
        Stat::incr('processed:' . (string) $this);
        Resque::getBackend()->del('worker:' . (string) $this);
    }

    /**
     * Generate a string representation of this worker.
     *
     * @return string String identifier for this worker instance.
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Output a given log message to STDOUT.
     *
     * @param string $message  Message to output.
     * @param int    $logLevel The logging level to capture
     */
    public function log($message, $logLevel = self::LOG_DEBUG)
    {
        if (! defined('STDOUT')) {
            return;
        }

        if ($logLevel > $this->logLevel) {
            return;
        }

        switch ($logLevel) {
            case self::LOG_DEBUG    : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_INFO     : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_NOTICE   : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_WARNING  : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_ERROR    : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_CRITICAL : fwrite(STDOUT, $message . PHP_EOL); break;
            case self::LOG_ALERT    : fwrite(STDOUT, $message . PHP_EOL); break;
            default: break;
        }
    }

    /**
     * Return an object describing the job this worker is currently working on.
     *
     * @return object Object with details of current job.
     */
    public function job()
    {
        $job = Resque::getBackend()->get('worker:' . (string) $this);
        if (!$job) {
            return array();
        } else {
            return json_decode($job, true);
        }
    }

    /**
     * Get a statistic belonging to this worker.
     *
     * @param  string $stat Statistic to fetch.
     * @return int    Statistic value.
     */
    public function getStat($stat)
    {
        return Stat::get($stat . ':' . (string) $this);
    }

    /**
     * @return int
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    public function setInterval($interval)
    {
        $this->interval = $interval;
    }

    public function getInterval()
    {
        return $this->interval;
    }
}
