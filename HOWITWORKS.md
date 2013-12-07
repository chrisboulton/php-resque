*For an overview of how to __use__ php-resque, see `README.md`.*

The following is a step-by-step breakdown of how php-resque operates.

## Enqueue Job ##

What happens when you call `Resque::enqueue()`?

1. `Resque::enqueue()` calls `Resque_Job::create()` with the same arguments it
   received.
2. `Resque_Job::create()` checks that your `$args` (the third argument) are
   either `null` or in an array
3. `Resque_Job::create()` generates a job ID (a "token" in most of the docs)
4. `Resque_Job::create()` pushes the job to the requested queue (first
   argument)
5. `Resque_Job::create()`, if status monitoring is enabled for the job (fourth
   argument), calls `Resque_Job_Status::create()` with the job ID as its only
   argument
6. `Resque_Job_Status::create()` creates a key in Redis with the job ID in its
   name, and the current status (as well as a couple of timestamps) as its
   value, then returns control to `Resque_Job::create()`
7. `Resque_Job::create()` returns control to `Resque::enqueue()`, with the job
   ID as a return value
8. `Resque::enqueue()` triggers the `afterEnqueue` event, then returns control
   to your application, again with the job ID as its return value

## Workers At Work ##

How do the workers process the queues?

1. `Resque_Worker::work()`, the main loop of the worker process, calls
   `Resque_Worker->reserve()` to check for a job
2. `Resque_Worker->reserve()` checks whether to use blocking pops or not (from
   `BLOCKING`), then acts accordingly:
  * Blocking Pop
    1. `Resque_Worker->reserve()` calls `Resque_Job::reserveBlocking()` with
       the entire queue list and the timeout (from `INTERVAL`) as arguments
    2. `Resque_Job::reserveBlocking()` calls `Resque::blpop()` (which in turn
       calls Redis' `blpop`, after prepping the queue list for the call, then
       processes the response for consistency with other aspects of the
       library, before finally returning control [and the queue/content of the
       retrieved job, if any] to `Resque_Job::reserveBlocking()`)
    3. `Resque_Job::reserveBlocking()` checks whether the job content is an
       array (it should contain the job's type [class], payload [args], and
       ID), and aborts processing if not
    4. `Resque_Job::reserveBlocking()` creates a new `Resque_Job` object with
       the queue and content as constructor arguments to initialize the job
       itself, and returns it, along with control of the process, to
       `Resque_Worker->reserve()`
  * Queue Polling
    1. `Resque_Worker->reserve()` iterates through the queue list, calling
       `Resque_Job::reserve()` with the current queue's name as the sole
       argument on each pass
    2. `Resque_Job::reserve()` passes the queue name on to `Resque::pop()`,
       which in turn calls Redis' `lpop` with the same argument, then returns
       control (and the job content, if any) to `Resque_Job::reserve()`
    3. `Resque_Job::reserve()` checks whether the job content is an array (as
       before, it should contain the job's type [class], payload [args], and
       ID), and aborts processing if not
    4. `Resque_Job::reserve()` creates a new `Resque_Job` object in the same
       manner as above, and also returns this object (along with control of
       the process) to `Resque_Worker->reserve()`
3. In either case, `Resque_Worker->reserve()` returns the new `Resque_Job`
   object, along with control, up to `Resque_Worker::work()`; if no job is
   found, it simply returns `FALSE`
  * No Jobs
    1. If blocking mode is not enabled, `Resque_Worker::work()` sleeps for
       `INTERVAL` seconds; it calls `usleep()` for this, so fractional seconds
       *are* supported
  * Job Reserved
    1. `Resque_Worker::work()` triggers a `beforeFork` event
    2. `Resque_Worker::work()` calls `Resque_Worker->workingOn()` with the new
       `Resque_Job` object as its argument
    3. `Resque_Worker->workingOn()` does some reference assignments to help keep
       track of the worker/job relationship, then updates the job status from
       `WAITING` to `RUNNING`
    4. `Resque_Worker->workingOn()` stores the new `Resque_Job` object's payload
       in a Redis key associated to the worker itself (this is to prevent the job
       from being lost indefinitely, but does rely on that PID never being
       allocated on that host to a different worker process), then returns control
       to `Resque_Worker::work()`
    5. `Resque_Worker::work()` forks a child process to run the actual `perform()`
    6. The next steps differ between the worker and the child, now running in
       separate processes:
      * Worker
        1. The worker waits for the job process to complete
        2. If the exit status is not 0, the worker calls `Resque_Job->fail()` with
           a `Resque_Job_DirtyExitException` as its only argument.
        3. `Resque_Job->fail()` triggers an `onFailure` event
        4. `Resque_Job->fail()` updates the job status from `RUNNING` to `FAILED`
        5. `Resque_Job->fail()` calls `Resque_Failure::create()` with the job
           payload, the `Resque_Job_DirtyExitException`, the internal ID of the
           worker, and the queue name as arguments
        6. `Resque_Failure::create()` creates a new object of whatever type has
           been set as the `Resque_Failure` "backend" handler; by default, this is
           a `Resque_Failure_Redis` object, whose constructor simply collects the
           data passed into `Resque_Failure::create()` and pushes it into Redis
           in the `failed` queue
        7. `Resque_Job->fail()` increments two failure counters in Redis: one for
           a total count, and one for the worker
        8. `Resque_Job->fail()` returns control to the worker (still in
           `Resque_Worker::work()`) without a value
      * Job
        1. The job calls `Resque_Worker->perform()` with the `Resque_Job` as its
           only argument.
        2. `Resque_Worker->perform()` sets up a `try...catch` block so it can
           properly handle exceptions by marking jobs as failed (by calling
           `Resque_Job->fail()`, as above)
        3. Inside the `try...catch`, `Resque_Worker->perform()` triggers an
           `afterFork` event
        4. Still inside the `try...catch`, `Resque_Worker->perform()` calls
           `Resque_Job->perform()` with no arguments
        5. `Resque_Job->perform()` calls `Resque_Job->getInstance()` with no
           arguments
        6. If `Resque_Job->getInstance()` has already been called, it returns the
           existing instance; otherwise:
        7. `Resque_Job->getInstance()` checks that the job's class (type) exists
           and has a `perform()` method; if not, in either case, it throws an
           exception which will be caught by `Resque_Worker->perform()`
        8. `Resque_Job->getInstance()` creates an instance of the job's class, and
           initializes it with a reference to the `Resque_Job` itself, the job's
           arguments (which it gets by calling `Resque_Job->getArguments()`, which
           in turn simply returns the value of `args[0]`, or an empty array if no
           arguments were passed), and the queue name
        9. `Resque_Job->getInstance()` returns control, along with the job class
           instance, to `Resque_Job->perform()`
        10. `Resque_Job->perform()` sets up its own `try...catch` block to handle
            `Resque_Job_DontPerform` exceptions; any other exceptions are passed
            up to `Resque_Worker->perform()`
        11. `Resque_Job->perform()` triggers a `beforePerform` event
        12. `Resque_Job->perform()` calls `setUp()` on the instance, if it exists
        13. `Resque_Job->perform()` calls `perform()` on the instance
        14. `Resque_Job->perform()` calls `tearDown()` on the instance, if it
            exists
        15. `Resque_Job->perform()` triggers an `afterPerform` event
        16. The `try...catch` block ends, suppressing `Resque_Job_DontPerform`
            exceptions by returning control, and the value `FALSE`, to
            `Resque_Worker->perform()`; any other situation returns the value
            `TRUE` along with control, instead
        17. The `try...catch` block in `Resque_Worker->perform()` ends
        18. `Resque_Worker->perform()` updates the job status from `RUNNING` to
            `COMPLETE`, then returns control, with no value, to the worker (again
            still in `Resque_Worker::work()`)
        19. `Resque_Worker::work()` calls `exit(0)` to terminate the job process
            cleanly
      * SPECIAL CASE: Non-forking OS (Windows)
        1. Same as the job above, except it doesn't call `exit(0)` when done
    7. `Resque_Worker::work()` calls `Resque_Worker->doneWorking()` with no
       arguments
    8. `Resque_Worker->doneWorking()` increments two processed counters in Redis:
       one for a total count, and one for the worker
    9. `Resque_Worker->doneWorking()` deletes the Redis key set in
       `Resque_Worker->workingOn()`, then returns control, with no value, to
       `Resque_Worker::work()`
4. `Resque_Worker::work()` returns control to the beginning of the main loop,
   where it will wait for the next job to become available, and start this
   process all over again