<?php

namespace Resque\Reserver;

/**
 * RandomQueueOrderReserver randomises the list of queues before then reserving a job off the first available queue.
 */
class RandomQueueOrderReserver extends QueueOrderReserver implements ReserverInterface
{
    /**
     * Gets the queues to reserve jobs from in random order.
     *
     * @return array
     */
    public function getQueues()
    {
        $queues = parent::getQueues();
        shuffle($queues);
        return $queues;
    }
}
