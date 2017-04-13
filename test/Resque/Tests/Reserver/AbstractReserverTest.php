<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\ReserverInterface;
use Resque;

abstract class AbstractReserverTest extends \Resque_Tests_TestCase
{
    /** @var string */
    protected $reserverName;

    /**
     * Gets a reserver instance configured with the given queues.
     *
     * @param array $queues
     * @return ReserverInstance
     */
    abstract protected function getReserver(array $queues = array());

    public function testGetName()
    {
        $this->assertEquals($this->reserverName, $this->getReserver()->getName());
    }

    public function testGetQueuesReturnsConfiguredQueues()
    {
        $queues = array(
            'queue_' . rand(1, 100),
            'queue_' . rand(101, 200),
            'queue_' . rand(201, 300),
        );
        $this->assertEquals($queues, $this->getReserver($queues)->getQueues());
    }

    public function testGetQueuesWithAsterixQueueReturnsAllQueuesFromRedisInSortedOrder()
    {
        $queues = array(
            'queue_b',
            'queue_c',
            'queue_d',
            'queue_a',
        );

        // register queues in redis
        foreach ($queues as $queue) {
            Resque::redis()->sadd('queues', $queue);
        }

        $expected = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
        );

        $this->assertEquals($expected, $this->getReserver(array('*'))->getQueues());
    }
}
