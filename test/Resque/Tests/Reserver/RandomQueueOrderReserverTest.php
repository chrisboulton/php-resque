<?php

namespace Resque\Tests\Reserver;

use Resque\Reserver\RandomQueueOrderReserver;
use Resque;

class RandomQueueOrderReserverTest extends \Resque_Tests_TestCase
{
    protected function getReserver(array $queues = array())
    {
        return new RandomQueueOrderReserver(new \Resque_Log(), $queues);
    }

    public function testGetName()
    {
        $this->assertEquals('RandomQueueOrderReserver', $this->getReserver()->getName());
    }

    public function testWaitAfterReservationAttemptReturnsTrue()
    {
        $this->assertTrue($this->getReserver()->waitAfterReservationAttempt());
    }

    private function assertQueuesAreShuffled(RandomQueueOrderReserver $reserver, array $queues)
    {
        // retrieve the queues 20 times
        $shuffledQueues = array();
        for ($x = 0; $x < 20; $x++) {
            $shuffledQueues[] = $reserver->getQueues();
        }

        $ordered = 0;
        foreach ($shuffledQueues as $shuffled) {
            // check if the order
            if ($shuffled === $queues) {
                $ordered++;
            }

            // check that the shuffled queues contain all the right elements though
            sort($shuffled);
            $this->assertEquals($queues, $shuffled);
        }

        // if all the shuffled queues were actually returned in sorted order then the shuffling is (unlikely) to be working
        $this->assertNotEquals(20, $ordered, "queues were ordered 20 times; queues not shuffled correctly");
    }

    public function testGetQueuesReturnsConfiguredQueuesInShuffledOrder()
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
            'queue_e',
            'queue_f',
        );

        $reserver = $this->getReserver($queues);

        $this->assertQueuesAreShuffled($reserver, $queues);
    }

    public function testGetQueuesWithAsterixQueueReturnsAllQueuesFromRedisInShuffledOrder()
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
            'queue_e',
            'queue_f',
        );

        // register queues in redis
        foreach ($queues as $queue) {
            Resque::redis()->sadd('queues', $queue);
        }

        $reserver = $this->getReserver(array('*'));

        $this->assertQueuesAreShuffled($reserver, $queues);
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

    public function testReserveReservesJobsFromRandomQueue()
    {
        $queues = array(
            'queue_a',
            'queue_b',
            'queue_c',
            'queue_d',
            'queue_e',
            'queue_f',
        );

        $reserver = $this->getReserver($queues);

        $jobsPerQueue = 5;

        // enqueue a bunch of jobs in each queue
        foreach ($queues as $queue) {
            for ($x = 0; $x < $jobsPerQueue; $x++) {
                $queuesForAllJobs[] = $queue;
                Resque::enqueue($queue, 'Test_Job');
            }
        }

        $totalJobs = count($queues) * $jobsPerQueue;

        // track the queue for each reserved job
        $reservedQueues = array();
        for ($x = 0; $x < $totalJobs; $x++) {
            $job = $reserver->reserve();
            $this->assertNotNull($job);
            $reservedQueues[] = $job->queue;
        }

        // if jobs are reserved randomly, then $queueOrder shouldn't be ordered
        $orderedQueues = $reservedQueues;
        sort($orderedQueues);
        $this->assertNotEquals($orderedQueues, $reservedQueues, "queues were ordered; queues not shuffled correctly");
    }
}
