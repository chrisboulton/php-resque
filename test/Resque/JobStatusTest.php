<?php

namespace Resque;

use Resque\Resque;
use Resque\Job;
use Resque\Job\Status;

/**
 * Status tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class JobStatusTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new Worker($this->resque, 'jobs');
    }

    public function testJobStatusCanBeTracked()
    {
        $token = $this->resque->enqueue('jobs', '\Test_Job', null, true);
        $status = new Status($this->resque, $token);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        $this->resque->enqueue('jobs', '\Test_Job', null, true);
        $job = $this->resque->reserveJob('jobs');
        $this->assertEquals(Status::STATUS_WAITING, $job->getStatus());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = $this->resque->enqueue('jobs', '\Test_Job', null, true);
        $status = new Status($this->resque, $token);
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $token = $this->resque->enqueue('jobs', '\Failing_Job', null, true);
        $job = $this->resque->reserve();
        $this->worker->workingOn($job);
        $status = new Status($this->resque, $token);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = $this->resque->enqueue('jobs', '\Failing_Job', null, true);
        $this->worker->work(0);
        $status = new Status($this->resque, $token);
        $this->assertEquals(Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = $this->resque->enqueue('jobs', '\Test_Job', null, true);
        $this->worker->work(0);
        $status = new Status($this->resque, $token);
        $this->assertEquals(Status::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token = $this->resque->enqueue('jobs', '\Test_Job', null, false);
        $status = new Status($this->resque, $token);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        $status = new Status($this->resque, 'test');
        $status->create('test');
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
        $status->stop();
        $this->assertFalse($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = $this->resque->enqueue('jobs', '\Test_Job', null, true);
        $job = $this->resque->reserve();

        // Mark this job as being worked on to ensure that the new status $this->resque, is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = $this->resque->reserveJob('jobs');
        $this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus());
    }
}
