<?php
/**
 * PID tracker for the forked worker job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Job_PID
{
	/**
	 * Create a new PID tracker item for the supplied job ID.
	 *
	 * @param string $id The ID of the job to track the PID of.
	 */
	public static function create($id)
	{
		Resque::redis()->set('job:' . $id . ':pid', (string)getmypid());
	}

	/**
	 * Fetch the PID for the process actually executing the job.
	 *
	 * @param string $id The ID of the job to get the PID of.
	 *
	 * @return int PID of the process doing the job (on non-forking OS, PID of the worker, otherwise forked PID).
	 */
	public static function get($id)
	{
		return (int)Resque::redis()->get('job:' . $id . ':pid');
	}

	/**
	 * Remove the PID tracker for the job.
	 *
	 * @param string $id The ID of the job to remove the tracker from.
	 */
	public static function del($id)
	{
		Resque::redis()->del('job:' . $id . ':pid');
	}
}

