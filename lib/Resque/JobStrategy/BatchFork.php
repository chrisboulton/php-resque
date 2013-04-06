<?php
/**
 * Same as Fork, except that it processed batches of jobs before forking
 *
 * @package     Resque/JobStrategy
 * @author      Chris Boulton <chris@bigcommerce.com>
 * @author      Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class Resque_JobStrategy_BatchFork extends Resque_JobStrategy_Fork
{
    /**
     * @var int How many to process per child.
     */
    private $perChild;

    /**
     * @param integer $perChild
     */
    public function __construct($perChild = 10)
    {
        $this->perChild = $perChild;
    }

    /**
     * Seperate the job from the worker via pcntl_fork
     *
     * @param Resque_Job $job
     */
    public function perform(Resque_Job $job)
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
