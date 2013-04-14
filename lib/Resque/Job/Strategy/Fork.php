<?php

namespace Resque\Job\Strategy;

use Resque\Resque;
use Resque\Worker;
use Resque\Job;

/**
 * Seperates the job execution environment from the worker via pcntl_fork
 *
 * @package		Resque/JobStrategy
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @author		Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Fork extends InProcess
{
    /**
     * @param int|null 0 for the forked child, the PID of the child for the parent, or null if no child.
     */
    public $child;

    /**
     * @param Worker $worker Instance that is starting jobs
     */
    public $worker;

    /**
     * Set the Worker instance
     *
     * @param Worker $worker
     */
    public function setWorker(Worker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * Seperate the job from the worker via pcntl_fork
     *
     * @param Job $job
     */
    public function perform(Job $job)
    {
        $this->child = $this->worker->resque->fork();

        // Forked and we're the child. Run the job.
        if ($this->child === 0) {
            parent::perform($job);
            exit(0);
        }

        // Parent process, sit and wait
        if ($this->child > 0) {
            $status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
            $this->worker->updateProcLine($status);
            $this->worker->log($status);

            // Wait until the child process finishes before continuing
            pcntl_wait($status);
            $exitStatus = pcntl_wexitstatus($status);
            if ($exitStatus !== 0) {
                $job->fail(new Job\DirtyExitException(
                    'Job exited with exit code ' . $exitStatus
                ));
            }
        }

        $this->child = null;
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently working
     */
    public function shutdown()
    {
        if (!$this->child) {
            $this->worker->log('No child to kill.');

            return;
        }

        $this->worker->log('Killing child at '.$this->child);
        if (exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->worker->log('Killing child at ' . $this->child);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        } else {
            $this->worker->log('Child ' . $this->child . ' not found, restarting.');
            $this->worker->shutdown();
        }
    }
}
