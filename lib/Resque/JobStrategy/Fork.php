<?php
/**
 * Seperates the job execution environment from the worker via pcntl_fork
 *
 * @package		Resque/JobStrategy
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @author		Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_JobStrategy_Fork extends Resque_JobStrategy_InProcess
{
	/**
	 * @param int|null 0 for the forked child, the PID of the child for the parent, or null if no child.
	 */
	protected $child;

	/**
	 * @param Resque_Worker Instance of Resque_Worker that is starting jobs
	 */
	protected $worker;

	/**
	 * Set the Resque_Worker instance
	 *
	 * @param Resque_Worker $worker
	 */
	public function setWorker(Resque_Worker $worker)
	{
		$this->worker = $worker;
	}

	/**
	 * Seperate the job from the worker via pcntl_fork
	 *
	 * @param Resque_Job $job
	 */
	public function perform(Resque_Job $job)
	{
		$this->child = $this->fork();

		// Forked and we're the child. Run the job.
		if ($this->child === 0) {
			parent::perform($job);
			exit(0);
		}

		// Parent process, sit and wait
		if($this->child > 0) {
			$status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
			$this->worker->updateProcLine($status);
			$this->worker->log($status, Resque_Worker::LOG_VERBOSE);

			// Wait until the child process finishes before continuing
			pcntl_wait($status);
			$exitStatus = pcntl_wexitstatus($status);
			if($exitStatus !== 0) {
				$job->fail(new Resque_Job_DirtyExitException(
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
			$this->worker->log('No child to kill.', Resque_Worker::LOG_VERBOSE);
			return;
		}

		$this->worker->log('Killing child at '.$this->child, Resque_Worker::LOG_VERBOSE);
		if(exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
			$this->worker->log('Killing child at ' . $this->child, Resque_Worker::LOG_VERBOSE);
			posix_kill($this->child, SIGKILL);
			$this->child = null;
		}
		else {
			$this->worker->log('Child ' . $this->child . ' not found, restarting.', Resque_Worker::LOG_VERBOSE);
			$this->worker->shutdown();
		}
	}

	/**
	 * Attempt to fork a child process from the parent to run a job in.
	 *
	 * Return values are those of pcntl_fork().
	 *
	 * @return int 0 for the forked child, or the PID of the child for the parent.
	 * @throws RuntimeException When pcntl_fork returns -1
	 */
	private function fork()
	{
		$pid = pcntl_fork();
		if($pid === -1) {
			throw new RuntimeException('Unable to fork child worker.');
		}

		return $pid;
	}
}
