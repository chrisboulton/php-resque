<?php

namespace Resque\Reserver;

use Resque_Job;
use Psr\Log\LoggerInterface;
use Resque;

abstract class AbstractReserver implements ReserverInterface
{
    /** @var array */
    private $queues;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     * @param array $queues The queues to reserve from. If this contains '*', then the queues are retrieved dynamically
     * from redis on each call to reserve().
     */
    public function __construct(LoggerInterface $logger, array $queues)
    {
        $this->logger = $logger;
        $this->queues = $queues;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueues()
    {
        if (in_array('*', $this->queues)) {
            $queues = Resque::queues();
    		sort($queues);
            return $queues;
        }

        return $this->queues;
    }

    /**
     * {@inheritDoc}
     */
    public function waitAfterReservationAttempt()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        $name = get_class($this);
        $name = str_replace(__NAMESPACE__, '', $name);
        return trim($name, '\\');
    }
}
