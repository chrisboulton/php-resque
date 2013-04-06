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
        $this->worker = new Worker('jobs');
    }

    public function testJobStatusCanBeTracked()
    {
        $token = Resque::enqueue('jobs', '\Test_Job', null, true);
        $status = new Status($token);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        Resque::enqueue('jobs', '\Test_Job', null, true);
        $job = Job::reserve('jobs');
        $this->assertEquals(Status::STATUS_WAITING, $job->getStatus());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = Resque::enqueue('jobs', '\Test_Job', null, true);
        $status = new Status($token);
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $token = Resque::enqueue('jobs', '\Failing_Job', null, true);
        $job = $this->worker->reserve();
        $this->worker->workingOn($job);
        $status = new Status($token);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = Resque::enqueue('jobs', '\Failing_Job', null, true);
        $this->worker->work(0);
        $status = new Status($token);
        $this->assertEquals(Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = Resque::enqueue('jobs', '\Test_Job', null, true);
        $this->worker->work(0);
        $status = new Status($token);
        $this->assertEquals(Status::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token = Resque::enqueue('jobs', '\Test_Job', null, false);
        $status = new Status($token);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        Status::create('test');
        $status = new Status('test');
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
        $status->stop();
        $this->assertFalse($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = Resque::enqueue('jobs', '\Test_Job', null, true);
        $job = $this->worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = Job::reserve('jobs');
        $this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus());
    }
}
