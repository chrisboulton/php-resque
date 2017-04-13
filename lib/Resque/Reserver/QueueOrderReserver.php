<?php

namespace Resque\Reserver;

use Resque_Job;

/**
 * QueueOrderReserver reserves jobs in the order that the queues is given. As long as jobs exist in a higher priority
 * queue, they will continue to be reserved before moving to the next lowest priority queue.
 *
 * For example: given queues A, B and C, all the jobs from queue A will be processed before moving onto queue B and
 * then after that queue C.
 *
 * This is the default reserver.
 */
class QueueOrderReserver extends AbstractReserver implements ReserverInterface
{
    /**
     * {@inheritDoc}
     */
    public function reserve()
    {
        foreach ($this->getQueues() as $queue) {
            $this->logger->debug("[{reserver}] Checking queue '{queue}' for jobs", array(
                'queue'    => $queue,
                'reserver' => $this->getName(),
            ));

            $job = Resque_Job::reserve($queue);
            if ($job) {
                $this->logger->info("[{reserver}] Found job on queue '{queue}'", array(
                    'queue'    => $queue,
                    'reserver' => $this->getName(),
                ));
                return $job;
            }
        }

        return null;
    }
}
