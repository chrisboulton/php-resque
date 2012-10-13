<?php
require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Resque_Job tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_JobTest extends Resque_Tests_TestCase
{
	protected $worker;

	public function setUp()
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new Resque_Worker('jobs');
		$this->worker->registerWorker();
	}

	public function testJobCanBeQueued()
	{
		$this->assertTrue((bool)Resque::enqueue('jobs', 'Test_Job'));
	}

	public function testQeueuedJobCanBeReserved()
	{
		Resque::enqueue('jobs', 'Test_Job');

		$job = Resque_Job::reserve('jobs');
		if($job == false) {
			$this->fail('Job could not be reserved.');
		}
		$this->assertEquals('jobs', $job->queue);
		$this->assertEquals('Test_Job', $job->payload['class']);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testObjectArgumentsCannotBePassedToJob()
	{
		$args = new stdClass;
		$args->test = 'somevalue';
		Resque::enqueue('jobs', 'Test_Job', $args);
	}

	public function testQueuedJobReturnsExactSamePassedInArguments()
	{
		$args = array(
			'int' => 123,
			'numArray' => array(
				1,
				2,
			),
			'assocArray' => array(
				'key1' => 'value1',
				'key2' => 'value2'
			),
		);
		Resque::enqueue('jobs', 'Test_Job', $args);
		$job = Resque_Job::reserve('jobs');

		$this->assertEquals($args, $job->getArguments());
	}

	public function testAfterJobIsReservedItIsRemoved()
	{
		Resque::enqueue('jobs', 'Test_Job');
		Resque_Job::reserve('jobs');
		$this->assertFalse(Resque_Job::reserve('jobs'));
	}

	public function testRecreatedJobMatchesExistingJob()
	{
		$args = array(
			'int' => 123,
			'numArray' => array(
				1,
				2,
			),
			'assocArray' => array(
				'key1' => 'value1',
				'key2' => 'value2'
			),
		);

		Resque::enqueue('jobs', 'Test_Job', $args);
		$job = Resque_Job::reserve('jobs');

		// Now recreate it
		$job->recreate();

		$newJob = Resque_Job::reserve('jobs');
		$this->assertEquals($job->payload['class'], $newJob->payload['class']);
		$this->assertEquals($job->payload['args'], $newJob->getArguments());
	}


	public function testFailedJobExceptionsAreCaught()
	{
		$payload = array(
			'class' => 'Failing_Job',
			'args' => null
		);
		$job = new Resque_Job('jobs', $payload);
		$job->worker = $this->worker;

		$this->worker->perform($job);

		$this->assertEquals(1, Resque_Stat::get('failed'));
		$this->assertEquals(1, Resque_Stat::get('failed:'.$this->worker));
	}

	/**
	 * @expectedException Resque_Exception
	 */
	public function testJobWithoutPerformMethodThrowsException()
	{
		Resque::enqueue('jobs', 'Test_Job_Without_Perform_Method');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}

	/**
	 * @expectedException Resque_Exception
	 */
	public function testInvalidJobThrowsException()
	{
		Resque::enqueue('jobs', 'Invalid_Job');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}
	
	public function testJobWithSetUpCallbackFiresSetUp()
	{
		$payload = array(
			'class' => 'Test_Job_With_SetUp',
			'args' => array(
				'somevar',
				'somevar2',
			),
		);
		$job = new Resque_Job('jobs', $payload);
		$job->perform();
		
		$this->assertTrue(Test_Job_With_SetUp::$called);
	}
	
	public function testJobWithTearDownCallbackFiresTearDown()
	{
		$payload = array(
			'class' => 'Test_Job_With_TearDown',
			'args' => array(
				'somevar',
				'somevar2',
			),
		);
		$job = new Resque_Job('jobs', $payload);
		$job->perform();
		
		$this->assertTrue(Test_Job_With_TearDown::$called);
	}
	
	public function testJobWithNamespace()
	{
	    Resque_Redis::prefix('php');
	    $queue = 'jobs';
	    $payload = array('another_value');
        Resque::enqueue($queue, 'Test_Job_With_TearDown', $payload);
        
        $this->assertEquals(Resque::queues(), array('jobs'));
        $this->assertEquals(Resque::size($queue), 1);
        
        Resque_Redis::prefix('resque');
        $this->assertEquals(Resque::size($queue), 0);        
	}
}