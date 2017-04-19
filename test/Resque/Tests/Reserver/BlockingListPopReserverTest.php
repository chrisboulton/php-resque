<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\BlockingListPopReserver;
use Resque;

/**
 * BlockingListPopReserver behaves the same as QueueOrderReserver but with different underlying implementation.
 */
class BlockingListPopReserverTest extends QueueOrderReserverTest
{
    protected $reserverName = 'BlockingListPopReserver';

    protected function getReserver(array $queues = array(), $timeout = 1)
    {
        return new BlockingListPopReserver(new \Resque_Log(), $queues, $timeout);
    }

    public function testWaitAfterReservationAttemptReturnsTrue()
    {
        $this->assertFalse($this->getReserver()->waitAfterReservationAttempt());
    }

    public function testReserverWhenNoJobsEnqueuedReturnsNull()
    {
        $queues = array(
            'queue_1',
            'queue_2',
            'queue_3',
        );

        $redisQueues = array(
            'queue:queue_1',
            'queue:queue_2',
            'queue:queue_3',
        );

        // hhvm doesn't respect the timeout arg for blpop, so we need to mock this command
        // https://github.com/facebook/hhvm/issues/6286
        $redis = $this->getMockBuilder('\Resque_Redis')
            ->disableOriginalConstructor()
            ->setMethods(['__call'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('blpop'), $this->equalTo(array($redisQueues, 1)))
            ->will($this->returnValue(null));

        $originalRedis = Resque::$redis;

        Resque::$redis = $redis;

        $this->assertNull($this->getReserver($queues)->reserve());

        Resque::$redis = $originalRedis;
    }

    public function testReserveCallsBlpopWithTimeout()
    {
        $timeout = rand(1, 100);

        $queues = array(
            'high',
            'medium',
            'low',
        );

        $redisQueues = array(
            'queue:high',
            'queue:medium',
            'queue:low',
        );

        $payload = array('class' => 'Test_Job');
        $item = array('resque:queue:high', json_encode($payload));

        $redis = $this->getMockBuilder('\Resque_Redis')
            ->disableOriginalConstructor()
            ->setMethods(['__call'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('blpop'), $this->equalTo(array($redisQueues, $timeout)))
            ->will($this->returnValue($item));

        $originalRedis = Resque::$redis;

        Resque::$redis = $redis;

        $job = $this->getReserver($queues, $timeout)->reserve();
        $this->assertEquals('high', $job->queue);
        $this->assertEquals($payload, $job->payload);

        Resque::$redis = $originalRedis;
    }
}
