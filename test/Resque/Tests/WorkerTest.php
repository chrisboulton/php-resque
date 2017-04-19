<?php

use Resque\Reserver\ReserverFactory;

/**
 * Resque_Worker tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_WorkerTest extends Resque_Tests_TestCase
{
	/**
	 * Gets a worker configured for the given queues.
	 *
	 * @param array $queues
	 * @return Resque_Worker
	 */
	private function getWorker(array $queues)
	{
		$logger = new Resque_Log();

		$reserverFactory = new ReserverFactory($logger);
		$reserver = $reserverFactory->createDefaultReserver($queues);

		$worker = new Resque_Worker($reserver, $queues);
		$worker->setLogger($logger);

		return $worker;
	}

	public function testWorkerRegistersInList()
	{
		$worker = $this->getWorker(array('*'));
		$worker->registerWorker();

		// Make sure the worker is in the list
		$this->assertTrue((bool)$this->redis->sismember('resque:workers', (string)$worker));
	}

	public function testGetAllWorkers()
	{
		$num = 3;
		// Register a few workers
		for($i = 0; $i < $num; ++$i) {
			$worker = $this->getWorker(array('queue_' . $i));
			$worker->registerWorker();
		}

		// Now try to get them
		$this->assertEquals($num, count(Resque_Worker::all()));
	}

	public function testGetWorkerById()
	{
		$worker = $this->getWorker(array('*'));
		$worker->registerWorker();

		$newWorker = Resque_Worker::find((string)$worker);
		$this->assertEquals((string)$worker, (string)$newWorker);
	}

	public function testInvalidWorkerDoesNotExist()
	{
		$this->assertFalse(Resque_Worker::exists('blah'));
	}

	public function testWorkerCanUnregister()
	{
		$worker = $this->getWorker(array('*'));
		$worker->registerWorker();
		$worker->unregisterWorker();

		$this->assertFalse(Resque_Worker::exists((string)$worker));
		$this->assertEquals(array(), Resque_Worker::all());
		$this->assertEquals(array(), $this->redis->smembers('resque:workers'));
	}

	public function testPausedWorkerDoesNotPickUpJobs()
	{
		$worker = $this->getWorker(array('*'));
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$worker->work(0);
		$this->assertEquals(0, Resque_Stat::get('processed'));
	}

	public function testResumedWorkerPicksUpJobs()
	{
		$worker = $this->getWorker(array('*'));
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$this->assertEquals(0, Resque_Stat::get('processed'));
		$worker->unPauseProcessing();
		$worker->work(0);
		$this->assertEquals(1, Resque_Stat::get('processed'));
	}

	public function testWorkerCanWorkOverMultipleQueues()
	{
		$worker = $this->getWorker(array(
			'queue1',
			'queue2',
		));
		$worker->registerWorker();
		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerWorksQueuesInSpecifiedOrder()
	{
		$worker = $this->getWorker(array(
			'high',
			'medium',
			'low',
		));
		$worker->registerWorker();

		// Queue the jobs in a different order
		Resque::enqueue('low', 'Test_Job_1');
		Resque::enqueue('high', 'Test_Job_2');
		Resque::enqueue('medium', 'Test_Job_3');

		// Now check we get the jobs back in the right order
		$job = $worker->reserve();
		$this->assertEquals('high', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('medium', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('low', $job->queue);
	}

	public function testWildcardQueueWorkerWorksAllQueues()
	{
		$worker = $this->getWorker(array('*'));
		$worker->registerWorker();

		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerDoesNotWorkOnUnknownQueues()
	{
		$worker = $this->getWorker(array('queue1'));
		$worker->registerWorker();
		Resque::enqueue('queue2', 'Test_Job');

		$this->assertFalse($worker->reserve());
	}

	public function testWorkerClearsItsStatusWhenNotWorking()
	{
		Resque::enqueue('jobs', 'Test_Job');
		$worker = $this->getWorker(array('jobs'));
		$job = $worker->reserve();
		$worker->workingOn($job);
		$worker->doneWorking();
		$this->assertEquals(array(), $worker->job());
	}

	public function testWorkerRecordsWhatItIsWorkingOn()
	{
		$worker = $this->getWorker(array('jobs'));
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new Resque_Job('jobs', $payload);
		$worker->workingOn($job);

		$job = $worker->job();
		$this->assertEquals('jobs', $job['queue']);
		if(!isset($job['run_at'])) {
			$this->fail('Job does not have run_at time');
		}
		$this->assertEquals($payload, $job['payload']);
	}

	public function testWorkerErasesItsStatsWhenShutdown()
	{
		Resque::enqueue('jobs', 'Test_Job');
		Resque::enqueue('jobs', 'Invalid_Job');

		$worker = $this->getWorker(array('jobs'));
		$worker->work(0);
		$worker->work(0);

		$this->assertEquals(0, $worker->getStat('processed'));
		$this->assertEquals(0, $worker->getStat('failed'));
	}

	public function testWorkerCleansUpDeadWorkersOnStartup()
	{
		// Register a good worker
		$goodWorker = $this->getWorker(array('jobs'));
		$goodWorker->registerWorker();
		$workerId = explode(':', $goodWorker);

		// Register some bad workers
		$worker = $this->getWorker(array('jobs'));
		$worker->setId($workerId[0].':1:jobs');
		$worker->registerWorker();

		$worker = $this->getWorker(array('high', 'low'));
		$worker->setId($workerId[0].':2:high,low');
		$worker->registerWorker();

		$this->assertEquals(3, count(Resque_Worker::all()));

		$goodWorker->pruneDeadWorkers();

		// There should only be $goodWorker left now
		$this->assertEquals(1, count(Resque_Worker::all()));
	}

	public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
	{
		// Register a bad worker on this machine
		$worker = $this->getWorker(array('jobs'));
		$workerId = explode(':', $worker);
		$worker->setId($workerId[0].':1:jobs');
		$worker->registerWorker();

		// Register some other false workers
		$worker = $this->getWorker(array('jobs'));
		$worker->setId('my.other.host:1:jobs');
		$worker->registerWorker();

		$this->assertEquals(2, count(Resque_Worker::all()));

		$worker->pruneDeadWorkers();

		// my.other.host should be left
		$workers = Resque_Worker::all();
		$this->assertEquals(1, count($workers));
		$this->assertEquals((string)$worker, (string)$workers[0]);
	}

	public function testWorkerFailsUncompletedJobsOnExit()
	{
		$worker = $this->getWorker(array('jobs'));
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new Resque_Job('jobs', $payload);

		$worker->workingOn($job);
		$worker->unregisterWorker();

		$this->assertEquals(1, Resque_Stat::get('failed'));
	}

    public function testBlockingListPop()
    {
        $worker = $this->getWorker(array('jobs'));
        $worker->registerWorker();

        Resque::enqueue('jobs', 'Test_Job_1');
        Resque::enqueue('jobs', 'Test_Job_2');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Test_Job_' . $i, $job->payload['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }
}
