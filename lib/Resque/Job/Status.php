<?php

namespace Resque\Job;

use Resque\Resque;

/**
 * Status tracker/information for a job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Status
{
    const STATUS_WAITING = 1;
    const STATUS_RUNNING = 2;
    const STATUS_FAILED = 3;
    const STATUS_COMPLETE = 4;

    /**
     * @var string The ID of the job this status class refers back to.
     */
    public $id;

    /**
     * @var mixed Cache variable if the status of this job is being monitored or not.
     * 	True/false when checked at least once or null if not checked yet.
     */
    public $isTracking = null;

    /**
     * @var array Array of statuses that are considered final/complete.
     */
    public $completeStatuses = array(
        self::STATUS_FAILED,
        self::STATUS_COMPLETE
    );

    /**
     * Setup a new instance of the job monitor class for the supplied job ID.
     *
     * @param string $id The ID of the job to manage the status for.
     */
    public function __construct($resque, $id)
    {
        $this->resque = $resque;
        $this->id = $id;
    }

    /**
     * Create a new status monitor item for the supplied job ID. Will create
     * all necessary keys in the backend to monitor the status of a job.
     *
     * @param string $id The ID of the job to monitor the status of.
     */
    public function create()
    {
        $statusPacket = array(
            'status' => self::STATUS_WAITING,
            'updated' => time(),
            'started' => time(),
        );
        $this->resque->getBackend()->set('job:' . $this->id . ':status', json_encode($statusPacket));
    }

    /**
     * Check if we're actually checking the status of the loaded job status
     * instance.
     *
     * @return boolean True if the status is being monitored, false if not.
     */
    public function isTracking()
    {
        if ($this->isTracking === false) {
            return false;
        }

        if (!$this->resque->getBackend()->exists((string) $this)) {
            $this->isTracking = false;

            return false;
        }

        $this->isTracking = true;

        return true;
    }

    /**
     * Update the status indicator for the current job with a new status.
     *
     * @param int $status The status of the job (see constants)
     */
    public function update($status)
    {
        if (!$this->isTracking()) {
            return;
        }

        $statusPacket = array(
            'status' => $status,
            'updated' => time(),
        );
        $this->resque->getBackend()->set((string) $this, json_encode($statusPacket));

        // Expire the status for completed jobs after 24 hours
        if (in_array($status, $this->completeStatuses)) {
            $this->resque->getBackend()->expire((string) $this, 86400);
        }
    }

    /**
     * Fetch the status for the job being monitored.
     *
     * @return mixed False if the status is not being monitored, otherwise the status as
     * 	as an integer, based on the constants.
     */
    public function get()
    {
        if (!$this->isTracking()) {
            return false;
        }

        $statusPacket = json_decode($this->resque->getBackend()->get((string) $this), true);
        if (!$statusPacket) {
            return false;
        }

        return $statusPacket['status'];
    }

    /**
     * Stop tracking the status of a job.
     */
    public function stop()
    {
        $this->resque->getBackend()->del((string) $this);
    }

    /**
     * Generate a string representation of this object.
     *
     * @return string String representation of the current job status class.
     */
    public function __toString()
    {
        return 'job:' . $this->id . ':status';
    }
}
