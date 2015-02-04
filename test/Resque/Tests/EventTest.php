<?php
/**
 * Resque_Event tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_EventTest extends Resque_Tests_TestCase
{
	private $callbacksHit = array();

	public function setUp()
	{
		Test_Job::$called = false;

		// Register a worker to test with
		$this->worker = new Resque_Worker('jobs');
		$this->worker->setLogger(new Resque_Log());
		$this->worker->registerWorker();
	}

	public function tearDown()
	{
		Resque_Event::clearListeners();
		$this->callbacksHit = array();
	}

	public function getEventTestJob()
	{
		$payload = array(
			'class' => 'Test_Job',
			'args' => array(
				'somevar',
			),
		);
		$job = new Resque_Job('jobs', $payload);
		$job->worker = $this->worker;
		return $job;
	}

	public function eventCallbackProvider()
	{
		return array(
			array('beforePerform', 'beforePerformEventCallback'),
			array('afterPerform', 'afterPerformEventCallback'),
			array('afterFork', 'afterForkEventCallback'),
		);
	}

	/**
	 * @dataProvider eventCallbackProvider
	 */
	public function testEventCallbacksFire($event, $callback)
	{
		Resque_Event::listen($event, array($this, $callback));

		$job = $this->getEventTestJob();
		$this->worker->perform($job);
		$this->worker->work(0);

		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testBeforeForkEventCallbackFires()
	{
		$event = 'beforeFork';
		$callback = 'beforeForkEventCallback';

		Resque_Event::listen($event, array($this, $callback));
		Resque::enqueue('jobs', 'Test_Job', array(
			'somevar'
		));
		$job = $this->getEventTestJob();
		$this->worker->work(0);
		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testBeforeEnqueueEventCallbackFires()
	{
		$event = 'beforeEnqueue';
		$callback = 'beforeEnqueueEventCallback';

		Resque_Event::listen($event, array($this, $callback));
		Resque::enqueue('jobs', 'Test_Job', array(
			'somevar'
		));
		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testBeforePerformEventCanStopWork()
	{
		$callback = 'beforePerformEventDontPerformCallback';
		Resque_Event::listen('beforePerform', array($this, $callback));

		$job = $this->getEventTestJob();

		$this->assertFalse($job->perform());
		$this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
		$this->assertFalse(Test_Job::$called, 'Job was still performed though Resque_Job_DontPerform was thrown');
	}

	public function testBeforeEnqueueEventStopsJobCreation()
	{
		$callback = 'beforeEnqueueEventDontCreateCallback';
		Resque_Event::listen('beforeEnqueue', array($this, $callback));
		Resque_Event::listen('afterEnqueue', array($this, 'afterEnqueueEventCallback'));

		$result = Resque::enqueue('test_job', 'TestClass');
		$this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
		$this->assertNotContains('afterEnqueueEventCallback', $this->callbacksHit, 'afterEnqueue was still called, even though it should not have been');
		$this->assertFalse($result);
	}

	public function testAfterEnqueueEventCallbackFires()
	{
		$callback = 'afterEnqueueEventCallback';
		$event    = 'afterEnqueue';

		Resque_Event::listen($event, array($this, $callback));
		Resque::enqueue('jobs', 'Test_Job', array(
			'somevar'
		));
		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testStopListeningRemovesListener()
	{
		$callback = 'beforePerformEventCallback';
		$event    = 'beforePerform';

		Resque_Event::listen($event, array($this, $callback));
		Resque_Event::stopListening($event, array($this, $callback));

		$job = $this->getEventTestJob();
		$this->worker->perform($job);
		$this->worker->work(0);

		$this->assertNotContains($callback, $this->callbacksHit,
			$event . ' callback (' . $callback .') was called though Resque_Event::stopListening was called'
		);
	}

	public function beforePerformEventDontPerformCallback($instance)
	{
		$this->callbacksHit[] = __FUNCTION__;
		throw new Resque_Job_DontPerform;
	}

	public function beforeEnqueueEventDontCreateCallback($queue, $class, $args, $track = false)
	{
		$this->callbacksHit[] = __FUNCTION__;
		throw new Resque_Job_DontCreate;
	}

	public function assertValidEventCallback($function, $job)
	{
		$this->callbacksHit[] = $function;
		if (!$job instanceof Resque_Job) {
			$this->fail('Callback job argument is not an instance of Resque_Job');
		}
		$args = $job->getArguments();
		$this->assertEquals($args[0], 'somevar');
	}

	public function afterEnqueueEventCallback($class, $args)
	{
		$this->callbacksHit[] = __FUNCTION__;
		$this->assertEquals('Test_Job', $class);
		$this->assertEquals(array(
			'somevar',
		), $args);
	}

	public function beforeEnqueueEventCallback($job)
	{
		$this->callbacksHit[] = __FUNCTION__;
	}

	public function beforePerformEventCallback($job)
	{
		$this->assertValidEventCallback(__FUNCTION__, $job);
	}

	public function afterPerformEventCallback($job)
	{
		$this->assertValidEventCallback(__FUNCTION__, $job);
	}

	public function beforeForkEventCallback($job)
	{
		$this->assertValidEventCallback(__FUNCTION__, $job);
	}

	public function afterForkEventCallback($job)
	{
		$this->assertValidEventCallback(__FUNCTION__, $job);
	}
}
