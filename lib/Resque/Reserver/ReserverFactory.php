<?php

namespace Resque\Reserver;

use Psr\Log\LoggerInterface;

class ReserverFactory
{
    /** @var string */
    const DEFAULT_RESERVER = 'queue_order';

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Creates a reserver given its name in snake case format.
     *
     * @param string $name
     * @return ReserverInterface
     * @throws UnknownReserverException
     */
    public function createReserverFromName($name, array $queues)
    {
        $parts = explode('_', $name);
        $parts = array_map(function ($word) {
            return ucfirst(strtolower($word));
        }, $parts);

        $methodName = 'create' . implode('', $parts) . 'Reserver';

        if (!method_exists($this, $methodName)) {
            throw new UnknownReserverException("Unknown reserver '$name' - could not find factory method $methodName");
        }

        return $this->$methodName($queues);
    }

    /**
     * Creates the default reserver.
     *
     * @param array $queues
     * @return ReserverInterface
     */
    public function createDefaultReserver(array $queues)
    {
        return $this->createReserverFromName(self::DEFAULT_RESERVER, $queues);
    }

    /**
     * @param array $queues
     * @return QueueOrderReserver
     */
    public function createQueueOrderReserver(array $queues)
    {
        return new QueueOrderReserver($this->logger, $queues);
    }

    /**
     * @param array $queues
     * @return RandomQueueOrderReserver
     */
    public function createRandomQueueOrderReserver(array $queues)
    {
        return new RandomQueueOrderReserver($this->logger, $queues);
    }

    /**
     * @param array $queues
     * @return BlockingListPopReserver
     */
    public function createBlockingListPopReserver(array $queues)
    {
        $timeout = getenv('BPLOP_TIMEOUT');
        if ($timeout === false) {
            $timeout = getenv('INTERVAL');
        }

        if ($timeout === false || $timeout < 0) {
            $timeout = BlockingListPopReserver::DEFAULT_TIMEOUT;
        }

        return new BlockingListPopReserver($this->logger, $queues, (int)$timeout);
    }
}
