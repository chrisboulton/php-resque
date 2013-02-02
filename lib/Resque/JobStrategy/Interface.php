<?php
/**
 * Interface that all job strategy backends should implement.
 *
 * @package		Resque/JobStrategy
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @author		Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
interface Resque_JobStrategy_Interface
{
	/**
	 * Set the Resque_Worker instance
	 *
	 * @param Resque_Worker $worker
	 */
	function setWorker(Resque_Worker $worker);

	/**
	 * Seperates the job execution context from the worker and calls $worker->perform($job).
	 *
	 * @param Resque_Job $job
	 */
	function perform(Resque_Job $job);

	/**
	 * Force an immediate shutdown of the worker, killing any child jobs
	 * currently working
	 */
	function shutdown();
}
