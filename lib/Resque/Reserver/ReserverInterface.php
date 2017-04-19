<?php

namespace Resque\Reserver;

use Resque_Job;

/**
 * A reserver implements a specific behaviour for reserving jobs from its queues.
 * Resque_Worker will call the reserve() method to obtain a reserved job.
 */
interface ReserverInterface
{
    /**
     * Gets the queues to reserve jobs from.
     *
     * @return array
     */
    public function getQueues();

    /**
     * Reserves a job.
     *
     * @return Resque_Job|null A job instance or null if not job was available to reserve.
     */
    public function reserve();

    /**
     * If there was no job available to reserve, should the worker wait before attempting to reserve a job again?
     *
     * @return bool
     */
    public function waitAfterReservationAttempt();

    /**
     * Gets a friendly name of this reserver.
     *
     * @return string
     */
    public function getName();
}
