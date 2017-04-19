<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\QueueOrderReserver;
use Resque;

class QueueOrderReserverTest extends AbstractReserverTest
{
    protected $reserverName = 'QueueOrderReserver';

    protected function getReserver(array $queues = array())
    {
        return new QueueOrderReserver(new \Resque_Log(), $queues);
    }

    public function testWaitAfterReservationAttemptReturnsTrue()
    {
        $this->assertTrue($this->getReserver()->waitAfterReservationAttempt());
    }

    public function testReserverWhenNoJobsEnqueuedReturnsNull()
    {
        $queues = array(
            'queue_1',
            'queue_2',
            'queue_3',
        );
        $this->assertNull($this->getReserver($queues)->reserve());
    }

    public function testReserveReservesJobsInSpecifiedQueueOrder()
    {
        $queues = array(
            'high',
            'medium',
            'low',
        );
        $reserver = $this->getReserver($queues);

        // Queue the jobs in a different order
        Resque::enqueue('low', 'Low_Job_1');
        Resque::enqueue('high', 'High_Job_1');
        Resque::enqueue('medium', 'Medium_Job_1');
        Resque::enqueue('medium', 'Medium_Job_2');
        Resque::enqueue('high', 'High_Job_2');
        Resque::enqueue('low', 'Low_Job_2');

        // Now check we get the jobs back in the right order
        $job = $reserver->reserve();
        $this->assertEquals('high', $job->queue);
        $this->assertEquals('High_Job_1', $job->payload['class']);

        $job = $reserver->reserve();
        $this->assertEquals('high', $job->queue);
        $this->assertEquals('High_Job_2', $job->payload['class']);

        $job = $reserver->reserve();
        $this->assertEquals('medium', $job->queue);
        $this->assertEquals('Medium_Job_1', $job->payload['class']);

        $job = $reserver->reserve();
        $this->assertEquals('medium', $job->queue);
        $this->assertEquals('Medium_Job_2', $job->payload['class']);

        $job = $reserver->reserve();
        $this->assertEquals('low', $job->queue);
        $this->assertEquals('Low_Job_1', $job->payload['class']);

        $job = $reserver->reserve();
        $this->assertEquals('low', $job->queue);
        $this->assertEquals('Low_Job_2', $job->payload['class']);
    }
}
