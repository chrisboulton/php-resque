<?php

namespace Resque\Reserver;

use Resque_Job;
use Psr\Log\LoggerInterface;

/**
 * BlockingListPopReserver uses the blocking list pop command in redis (https://redis.io/commands/blpop) to wait for a
 * job to become available on any of the given queues.
 * This also behaves similarly to QueueOrderReserver in that the queues are checked in the order they are given.
 *
 * Environment variables:
 * - BLPOP_TIMEOUT: The maximum time in seconds that the bplop command should block while waiting for a job.
 * upon timeout, the worker will attempt to immediately reserve a job again. If zero is specified, the command will
 * block indefinitely. If not specified, the value of the INTERVAL variable will be used which defaults to 5 seconds.
 */
class BlockingListPopReserver extends AbstractReserver implements ReserverInterface
{
    /** @var int */
    const DEFAULT_TIMEOUT = 5;

    /**
     * @param LoggerInterface $logger
     * @param array $queues The queues to reserve from. If null, then the queues are retrieved dynamically from redis
     * on each call to reserve().
     * @param int $timeout The number of seconds to wait for a job to be enqueued. A timeout of zero will block
     * indefinitely.
     */
    public function __construct(LoggerInterface $logger, array $queues, $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->timeout = $timeout;
        parent::__construct($logger, $queues);
    }

    /**
     * {@inheritDoc}
     */
    public function reserve()
    {
        $job = Resque_Job::reserveBlocking($this->getQueues(), $this->timeout);
        if ($job) {
            $this->logger->info("[{reserver}] Found job on queue '{queue}'", array(
                'queue'    => $job->queue,
                'reserver' => $this->getName(),
            ));
            return $job;
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function waitAfterReservationAttempt()
    {
        return false;
    }
}
