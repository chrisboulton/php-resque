<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\ReserverFactory;
use Resque;

class ReserverFactoryTest extends \PHPUnit_Framework_TestCase
{
    private function getFactory()
    {
        return new ReserverFactory(new \Resque_Log());
    }

    /**
     * @expectedException Resque\Reserver\UnknownReserverException
     * @expectedExceptionMessage Unknown reserver 'foo'
     */
    public function testCreateReserverFromNameThrowsExceptionForUnknownReserver()
    {
        $this->getFactory()->createReserverFromName('foo', array());
    }

    public function createReserverFromNameDataProvider()
    {
        return array(
            array('queue_order', '\Resque\Reserver\QueueOrderReserver'),
            array('RANDOM_QUEUE_ORDER', '\Resque\Reserver\RandomQueueOrderReserver'),
            array('Blocking_List_Pop', '\Resque\Reserver\BlockingListPopReserver'),
        );
    }

    /**
     * @dataProvider createReserverFromNameDataProvider
     */
    public function testCreateReserverFromNameCreatesExpectedReserver($name, $expectedReserver)
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
        );

        $reserver = $this->getFactory()->createReserverFromName($name, $queues);
        $this->assertInstanceOf($expectedReserver, $reserver);

        // account for shuffling by RandomQueueOrderReserver
        $actualQueues = $reserver->getQueues();
        sort($actualQueues);

        $this->assertEquals($queues, $actualQueues);
    }

    public function testCreateDefaultReserverCreatesExpectedReserver()
    {
        $reserver = $this->getFactory()->createDefaultReserver(array());
        $this->assertInstanceOf('\Resque\Reserver\QueueOrderReserver', $reserver);
    }
}
