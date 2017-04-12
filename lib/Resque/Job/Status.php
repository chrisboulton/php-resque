<?php
/**
 * Status tracker/information for a job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Job_Status
{
	const STATUS_WAITING = 1;
	const STATUS_RUNNING = 2;
	const STATUS_FAILED = 3;
	const STATUS_COMPLETE = 4;

	/**
	 * @var string The ID of the job this status class refers back to.
	 */
	private $id;

	/**
	 * @var mixed Cache variable if the status of this job is being monitored or not.
	 * 	True/false when checked at least once or null if not checked yet.
	 */
	private $isTracking = null;

	/**
	 * @var array Array of statuses that are considered final/complete.
	 */
	private static $completeStatuses = array(
		self::STATUS_FAILED,
		self::STATUS_COMPLETE
	);

	/**
	 * Setup a new instance of the job monitor class for the supplied job ID.
	 *
	 * @param string $id The ID of the job to manage the status for.
	 */
	public function __construct($id)
	{
		$this->id = self::generateId($id);
	}

	/**
	 * Create a new status monitor item for the supplied job ID. Will create
	 * all necessary keys in Redis to monitor the status of a job.
	 *
	 * @param string $id The ID of the job to monitor the status of.
	 */
	public static function create($id)
	{
		$statusPacket = array(
			'status' => self::STATUS_WAITING,
			'updated' => time(),
			'started' => time()
		);
		Resque::redis()->set(self::generateId($id), json_encode($statusPacket));
	}

	/**
	 * Check if we're actually checking the status of the loaded job status
	 * instance.
	 *
	 * @return boolean True if the status is being monitored, false if not.
	 */
	public function isTracking()
	{
		if($this->isTracking === false) {
			return false;
		}

		if(!Resque::redis()->exists($this->id)) {
			$this->isTracking = false;
			return false;
		}

		$this->isTracking = true;
		return true;
	}

	/**
	 * Update the status indicator for the current job with a new status.
	 *
	 * @param int The status of the job (see constants in Resque_Job_Status)
	 */
	public function update($status)
	{
		$status = (int)$status;

		if(!$this->isTracking()) {
			return;
		}

		if($status < 1 || $status > 4) {
			return;
		}

		$statusPacket = array(
			'status' => $status,
			'updated' => time(),
			'started' => $this->fetch('started')
		);
		Resque::redis()->set($this->id, json_encode($statusPacket));

		// Expire the status for completed jobs after 24 hours
		if(in_array($status, self::$completeStatuses)) {
			Resque::redis()->expire($this->id, 86400);
		}
	}

	/**
	 * Fetch the status for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the status as
	 * 	as an integer, based on the Resque_Job_Status constants.
	 */
	public function get()
	{
		return $this->status();
	}

	/**
	 * Fetch the status for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the status as
	 * 	as an integer, based on the Resque_Job_Status constants.
	 */
	public function status()
	{
		return $this->fetch('status');
	}

	/**
	 * Fetch the updated timestamp for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the updated timestamp
	 */
	public function updated()
	{
		return $this->fetch('updated');
	}

	/**
	 * Fetch the started timestamp for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the created timestamp
	 */
	public function started()
	{
		return $this->fetch('started');
	}

	/**
	 * Stop tracking the status of a job.
	 */
	public function stop()
	{
		Resque::redis()->del($this->id);
	}

	/**
	 * Generate a string representation of this object.
	 *
	 * @return string String representation of the current job status class.
	 */
	public function __toString()
	{
		return $this->id;
	}

	/**
	 * generate job status id key in a consistent manner
     *
	 * @return string String redis key for the current job status
	 */
	protected static function generateId($id)
	{
		return 'job:' . $id . ':status';
	}

	/**
	 * Fetch the status packet for the job being monitored.
	 * @param optional string $field The field to get from the status packet
	 *
	 * @return mixed False if the status is not being monitored, otherwise the status packet array or the individual field
	 */
	protected function fetch($field = false)
	{
		$statusPacket = Resque::redis()->get($this->id);
		if($statusPacket) {
			$statusPacket = json_decode($statusPacket, true);
			if($field) {
				if(isset($statusPacket[$field])) {
					return (int)$statusPacket[$field];
				}
			} else {
				return $statusPacket;
			}
		}
		return false;
	}
}
