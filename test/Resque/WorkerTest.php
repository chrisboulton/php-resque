<?php

namespace Resque;

/**
 * Worker tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class WorkerTest extends TestCase
{
    public function testWorkerRegistersInList()
    {
        $worker = new Worker($this->resque, '*');
        $this->resque->registerWorker($worker);

        // Make sure the worker is in the list
        $this->assertTrue((bool) $this->redis->sismember('resque:workers', (string) $worker));
    }

    public function testGetAllWorkers()
    {
        $num = 3;
        // Register a few workers
        for ($i = 0; $i < $num; ++$i) {
            $worker = new Worker($this->resque, 'queue_' . $i);
            $this->resque->registerWorker($worker);
        }

        // Now try to get them
        $this->assertEquals($num, count($this->resque->workers()));
    }

    public function testGetWorkerById()
    {
        $worker = new Worker($this->resque, '*');
        $this->resque->registerWorker($worker);

        $newWorker = $this->resque->findWorker((string) $worker);
        $this->assertEquals((string) $worker, (string) $newWorker);
    }

    public function testInvalidWorkerDoesNotExist()
    {
        $this->assertFalse($this->resque->workerExists('blah'));
    }

    public function testWorkerCanUnregister()
    {
        $worker = new Worker($this->resque, '*');
        $this->resque->registerWorker($worker);
        $this->resque->unregisterWorker($worker);

        $this->assertFalse($this->resque->workerExists((string) $worker));
        $this->assertEquals(array(), $this->resque->workers());
        $this->assertEquals(array(), $this->redis->smembers('resque:workers'));
    }

    public function testPausedWorkerDoesNotPickUpJobs()
    {
        $worker = new Worker($this->resque, '*');
        $worker->pauseProcessing();
        $this->resque->enqueue('jobs', 'Test_Job');
        $worker->work(0);
        $worker->work(0);
        $this->assertEquals(0, $this->resque->getStat()->get('processed'));
    }

    public function testResumedWorkerPicksUpJobs()
    {
        $worker = new Worker($this->resque, '*');
        $worker->pauseProcessing();
        $this->resque->enqueue('jobs', 'Test_Job');
        $worker->work(0);
        $this->assertEquals(0, $this->resque->getStat()->get('processed'));
        $worker->unPauseProcessing();
        $worker->work(0);
        $this->assertEquals(1, $this->resque->getStat()->get('processed'));
    }

    public function testWorkerCanWorkOverMultipleQueues()
    {
        $worker = new Worker($this->resque, array(
            'queue1',
            'queue2'
        ));
        $this->resque->registerWorker($worker);
        $this->resque->enqueue('queue1', 'Test_Job_1');
        $this->resque->enqueue('queue2', 'Test_Job_2');

        $job = $this->resque->reserve();
        $this->assertEquals('queue1', $job->queue);

        $job = $this->resque->reserve();
        $this->assertEquals('queue2', $job->queue);
    }

    public function testWorkerWorksQueuesInSpecifiedOrder()
    {
        $worker = new Worker($this->resque, array(
            'high',
            'medium',
            'low'
        ));
        $this->resque->registerWorker($worker);

        // Queue the jobs in a different order
        $this->resque->enqueue('low', 'Test_Job_1');
        $this->resque->enqueue('high', 'Test_Job_2');
        $this->resque->enqueue('medium', 'Test_Job_3');

        // Now check we get the jobs back in the right order
        $job = $this->resque->reserve($worker->queues);
        $this->assertEquals('high', $job->queue);

        $job = $this->resque->reserve($worker->queues);
        $this->assertEquals('medium', $job->queue);

        $job = $this->resque->reserve($worker->queues);
        $this->assertEquals('low', $job->queue);
    }

    public function testWildcardQueueWorkerWorksAllQueues()
    {
        $worker = new Worker($this->resque, '*');
        $this->resque->registerWorker($worker);

        $this->resque->enqueue('queue1', 'Test_Job_1');
        $this->resque->enqueue('queue2', 'Test_Job_2');

        $job = $this->resque->reserve();
        $this->assertEquals('queue1', $job->queue);

        $job = $this->resque->reserve();
        $this->assertEquals('queue2', $job->queue);
    }

    public function testWorkerDoesNotWorkOnUnknownQueues()
    {
        $worker = new Worker($this->resque, 'queue1');
        $this->resque->registerWorker($worker);
        $this->resque->enqueue('queue2', 'Test_Job');

        $this->assertFalse($this->resque->reserve($worker->queues));
    }

    public function testWorkerClearsItsStatusWhenNotWorking()
    {
        $this->resque->enqueue('jobs', 'Test_Job');
        $worker = new Worker($this->resque, 'jobs');
        $job = $this->resque->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();
        $this->assertEquals(array(), $worker->job());
    }

    public function testWorkerRecordsWhatItIsWorkingOn()
    {
        $worker = new Worker($this->resque, 'jobs');
        $this->resque->registerWorker($worker);

        $payload = array(
            'class' => 'Test_Job'
        );
        $job = new Job($this->resque, 'jobs', $payload);
        $worker->workingOn($job);

        $job = $worker->job();
        $this->assertEquals('jobs', $job['queue']);
        if (!isset($job['run_at'])) {
            $this->fail('Job does not have run_at time');
        }
        $this->assertEquals($payload, $job['payload']);
    }

    public function testWorkerErasesItsStatsWhenShutdown()
    {
        $this->resque->enqueue('jobs', 'Test_Job');
        $this->resque->enqueue('jobs', 'Invalid_Job');

        $worker = new Worker($this->resque, 'jobs');
        $worker->work(0);
        $worker->work(0);

        $this->assertEquals(0, $worker->getStat('processed'));
        $this->assertEquals(0, $worker->getStat('failed'));
    }

    public function testWorkerCleansUpDeadWorkersOnStartup()
    {
        // Register a good worker
        $goodWorker = new Worker($this->resque, 'jobs');
        $this->resque->registerWorker($goodWorker);
        $workerId = explode(':', $goodWorker);

        // Register some bad workers
        $worker = new Worker($this->resque, 'jobs');
        $worker->setId($workerId[0].':1:jobs');
        $this->resque->registerWorker($worker);

        $worker = new Worker($this->resque, array('high', 'low'));
        $worker->setId($workerId[0].':2:high,low');
        $this->resque->registerWorker($worker);

        $this->assertEquals(3, count($this->resque->workers()));

        $goodWorker->pruneDeadWorkers();

        // There should only be $goodWorker left now
        $this->assertEquals(1, count($this->resque->workers()));
    }

    public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
    {
        // Register a bad worker on this machine
        $worker = new Worker($this->resque, 'jobs');
        $workerId = explode(':', $worker);
        $worker->setId($workerId[0].':1:jobs');
        $this->resque->registerWorker($worker);

        // Register some other false workers
        $worker = new Worker($this->resque, 'jobs');
        $worker->setId('my.other.host:1:jobs');
        $this->resque->registerWorker($worker);

        $this->assertEquals(2, count($this->resque->workers()));

        $worker->pruneDeadWorkers();

        // my.other.host should be left
        $workers = $this->resque->workers();
        $this->assertEquals(1, count($workers));
        $this->assertEquals((string) $worker, (string) $workers[0]);
    }

    public function testWorkerFailsUncompletedJobsOnExit()
    {
        $worker = new Worker($this->resque, 'jobs');
        $this->resque->registerWorker($worker);

        $payload = array(
            'class' => 'Test_Job'
        );
        $job = new Job($this->resque, 'jobs', $payload);

        $worker->workingOn($job);
        $this->resque->unregisterWorker($worker);

        $this->assertEquals(1, $this->resque->getStat()->get('failed'));
    }
}
