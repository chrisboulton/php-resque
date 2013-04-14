<?php

namespace Resque\Job\Strategy;

use Resque\Job;

/**
 * Same as Fork, except that it processed batches of jobs before forking
 *
 * @package     Resque/JobStrategy
 * @author      Chris Boulton <chris@bigcommerce.com>
 * @author      Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class BatchFork extends Fork
{
    /**
     * @var int How many to process per child.
     */
    public $perChild;

    /**
     * @param integer $perChild
     */
    public function __construct($perChild = 10)
    {
        $this->perChild = $perChild;
    }

    /**
     * Separate the job from the worker via pcntl_fork
     *
     * @param \Resque\Job $job
     */
    public function perform(Job $job)
    {
        if (! $this->perChild || ($this->worker->getProcessed() > 0 && $this->worker->getProcessed() % $this->perChild !== 0)) {
            $status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
            $this->worker->updateProcLine($status);
            $this->worker->log($status);
            $this->worker->perform($job);
        } else {
            parent::perform($job);
        }
    }
}
