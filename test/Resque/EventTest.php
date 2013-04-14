<?php

namespace Resque;

/**
 * Event tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class EventTest extends TestCase
{
    private $callbacksHit = array();

    public function setUp()
    {
        parent::setUp();

        \Test_Job::$called = false;

        // Register a worker to test with
        $this->worker = new Worker($this->resque, 'jobs');
        $this->resque->registerWorker($this->worker);
    }

    public function tearDown()
    {
        $this->callbacksHit = array();
    }

    public function getEventTestJob()
    {
        $payload = array(
            'class' => '\Test_Job',
            'args' => array(
                'somevar',
            ),
        );

        $job = new Job($this->resque, 'jobs', $payload);
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
        $this->resque->getEvent()->listen($event, array($this, $callback));

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testBeforeForkEventCallbackFires()
    {
        $event = 'beforeFork';
        $callback = 'beforeForkEventCallback';

        $this->resque->getEvent()->listen($event, array($this, $callback));
        $this->resque->enqueue('jobs', '\Test_Job', array(
            'somevar'
        ));
        $this->getEventTestJob();
        $this->worker->work(0);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testBeforePerformEventCanStopWork()
    {
        $callback = 'beforePerformEventDontPerformCallback';
        $this->resque->getEvent()->listen('beforePerform', array($this, $callback));

        $job = $this->getEventTestJob();

        $this->assertFalse($job->perform());
        $this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
        $this->assertFalse(\Test_Job::$called, 'Job was still performed though Job\DontPerform was thrown');
    }

    public function testAfterEnqueueEventCallbackFires()
    {
        $callback = 'afterEnqueueEventCallback';
        $event = 'afterEnqueue';

        $this->resque->getEvent()->listen($event, array($this, $callback));
        $this->resque->enqueue('jobs', '\Test_Job', array(
            'somevar'
        ));
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
    }

    public function testStopListeningRemovesListener()
    {
        $callback = 'beforePerformEventCallback';
        $event = 'beforePerform';

        $this->resque->getEvent()->listen($event, array($this, $callback));
        $this->resque->getEvent()->stopListening($event, array($this, $callback));

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertNotContains($callback, $this->callbacksHit,
            $event . ' callback (' . $callback .') was called though $this->resque->getEvent()->stopListening was called'
        );
    }

    public function beforePerformEventDontPerformCallback()
    {
        $this->callbacksHit[] = __FUNCTION__;
        throw new Job\DontPerform;
    }

    public function assertValidEventCallback($function, $job)
    {
        $this->callbacksHit[] = $function;
        if (!$job instanceof Job) {
            $this->fail('Callback job argument is not an instance of Job');
        }
        $args = $job->getArguments();
        $this->assertEquals($args[0], 'somevar');
    }

    public function afterEnqueueEventCallback($class, $args)
    {
        $this->callbacksHit[] = __FUNCTION__;
        $this->assertEquals('\Test_Job', $class);
        $this->assertEquals(array(
            'somevar',
        ), $args);
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
