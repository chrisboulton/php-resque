<?php
/**
 * Resque job instance creation event
 *
 * @package		Resque/Event
 * @author		Chris Boulton <chris.boulton@interspire.com>
 * @copyright	(c) 2010 Chris Boulton
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Event_CreateInstance
{
	/**
	 * @var Resque_Job The job that triggered the event
	 */
	protected $job;

	/**
	 * @var object Instance of the object that $this->job belongs to
	 */
	protected $instance;

	/**
	 * Instantiate a new instance of the event
	 *
	 * @param Resque_Job $job The job that triggered the event
	 */
	public function __construct($job)
	{
		$this->job = $job;
	}

	/**
	 * Get the Resque_Job instance that triggered the event.
	 *
	 * @return Resque_Job Instance of the job that triggered the event.
	 */
	public function getJob()
	{
		return $this->job;
	}

	/**
	 * Set the instantiated object for $this->job that will be performing work.
	 */
	public function setInstance($instance)
	{
		$this->instance = $instance;
	}

	/**
	 * Get the instantiated object for $this->job that will be performing work, or null
	 *
	 * @return object Instance of the object that $this->job belongs to
	 */
	public function getInstance()
	{
		return $this->instance ?: null;
	}
}
?>
