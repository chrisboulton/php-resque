php-resque: PHP Resque Worker (and Enqueue) [![Build Status](https://secure.travis-ci.org/chrisboulton/php-resque.png)](http://travis-ci.org/chrisboulton/php-resque)
===========================================

Resque is a Redis-backed library for creating background jobs, placing
those jobs on multiple queues, and processing them later.

## Background ##

Resque was pioneered and is developed by the fine folks at GitHub (yes,
I am a kiss-ass), and written in Ruby. What you're seeing here is an
almost direct port of the Resque worker and enqueue system to PHP.

For more information on Resque, visit the official GitHub project:
 <http://github.com/defunkt/resque/>

For further information, see the launch post on the GitHub blog:
 <http://github.com/blog/542-introducing-resque>

The PHP port does NOT include its own web interface for viewing queue
stats, as the data is stored in the exact same expected format as the
Ruby version of Resque.

The PHP port provides much the same features as the Ruby version:

* Workers can be distributed between multiple machines
* Includes support for priorities (queues)
* Resilient to memory leaks (fork)
* Expects failure

It also supports the following additional features:

* Has the ability to track the status of jobs
* Will mark a job as failed, if a forked child running a job does
not exit with a status code as 0
* Has built in support for `setUp` and `tearDown` methods, called
pre and post jobs

## Requirements ##

* PHP 5.2+
* Redis 2.2+

## Jobs ##

### Queueing Jobs ###

Jobs are queued as follows:

    require_once 'lib/Resque.php';

	// Required if redis is located elsewhere
	Resque::setBackend('localhost:6379');

	$args = array(
		'name' => 'Chris'
	);
	Resque::enqueue('default', 'My_Job', $args);

### Defining Jobs ###

Each job should be in it's own class, and include a `perform` method.

	class My_Job
	{
		public function perform()
		{
			// Work work work
			echo $this->args['name'];
		}
	}

When the job is run, the class will be instantiated and any arguments
will be set as an array on the instantiated object, and are accessible
via `$this->args`.

Any exception thrown by a job will result in the job failing - be
careful here and make sure you handle the exceptions that shouldn't
result in a job failing.

Jobs can also have `setUp` and `tearDown` methods. If a `setUp` method
is defined, it will be called before the `perform` method is run.
The `tearDown` method if defined, will be called after the job finishes.

	class My_Job
	{
		public function setUp()
		{
			// ... Set up environment for this job
		}
		
		public function perform()
		{
			// .. Run job
		}
		
		public function tearDown()
		{
			// ... Remove environment for this job
		}
	}

### Tracking Job Statuses ###

php-resque has the ability to perform basic status tracking of a queued
job. The status information will allow you to check if a job is in the
queue, currently being run, has finished, or failed.

To track the status of a job, pass `true` as the fourth argument to
`Resque::enqueue`. A token used for tracking the job status will be
returned:

	$token = Resque::enqueue('default', 'My_Job', $args, true);
	echo $token;

To fetch the status of a job:

	$status = new Resque_Job_Status($token);
	echo $status->get(); // Outputs the status

Job statuses are defined as constants in the `Resque_Job_Status` class.
Valid statuses include:

* `Resque_Job_Status::STATUS_WAITING` - Job is still queued
* `Resque_Job_Status::STATUS_RUNNING` - Job is currently running
* `Resque_Job_Status::STATUS_FAILED` - Job has failed
* `Resque_Job_Status::STATUS_COMPLETE` - Job is complete
* `false` - Failed to fetch the status - is the token valid?

Statuses are available for up to 24 hours after a job has completed
or failed, and are then automatically expired. A status can also
forcefully be expired by calling the `stop()` method on a status
class.

## Workers ##

Workers work in the exact same way as the Ruby workers. For complete
documentation on workers, see the original documentation.

A basic "up-and-running" resque.php file is included that sets up a
running worker environment is included in the root directory.

The exception to the similarities with the Ruby version of resque is
how a worker is initially setup. To work under all environments,
not having a single environment such as with Ruby, the PHP port makes
*no* assumptions about your setup.

To start a worker, it's very similar to the Ruby version:

    $ QUEUE=file_serve php resque.php

It's your responsibility to tell the worker which file to include to get
your application underway. You do so by setting the `APP_INCLUDE` environment
variable:

   $ QUEUE=file_serve APP_INCLUDE=../application/init.php php resque.php

Getting your application underway also includes telling the worker your job
classes, by means of either an autoloader or including them.

### Logging ###

The port supports the same environment variables for logging to STDOUT.
Setting `VERBOSE` will print basic debugging information and `VVERBOSE`
will print detailed information.

    $ VERBOSE QUEUE=file_serve php resque.php
    $ VVERBOSE QUEUE=file_serve php resque.php

### Priorities and Queue Lists ###

Similarly, priority and queue list functionality works exactly
the same as the Ruby workers. Multiple queues should be separated with
a comma, and the order that they're supplied in is the order that they're
checked in.

As per the original example:

	$ QUEUE=file_serve,warm_cache php resque.php

The `file_serve` queue will always be checked for new jobs on each
iteration before the `warm_cache` queue is checked.

### Running All Queues ###

All queues are supported in the same manner and processed in alphabetical
order:

    $ QUEUE=* php resque.php

### Running Multiple Workers ###

Multiple workers ca be launched and automatically worked by supplying
the `COUNT` environment variable:

	$ COUNT=5 php resque.php

### Forking ###

Similarly to the Ruby versions, supported platforms will immediately
fork after picking up a job. The forked child will exit as soon as
the job finishes.

The difference with php-resque is that if a forked child does not
exit nicely (PHP error or such), php-resque will automatically fail
the job.

### Signals ###

Signals also work on supported platforms exactly as in the Ruby
version of Resque:

* `QUIT` - Wait for child to finish processing then exit
* `TERM` / `INT` - Immediately kill child then exit
* `USR1` - Immediately kill child but don't exit
* `USR2` - Pause worker, no new jobs will be processed
* `CONT` - Resume worker.

### Process Titles/Statuses ###

The Ruby version of Resque has a nifty feature whereby the process
title of the worker is updated to indicate what the worker is doing,
and any forked children also set their process title with the job
being run. This helps identify running processes on the server and
their resque status.

**PHP does not have this functionality by default.**

A PECL module (<http://pecl.php.net/package/proctitle>) exists that
adds this funcitonality to PHP, so if you'd like process titles updated,
install the PECL module as well. php-resque will detect and use it.

## Event/Hook System ##

php-resque has a basic event system that can be used by your application
to customize how some of the php-resque internals behave.

You listen in on events (as listed below) by registering with `Resque_Event`
and supplying a callback that you would like triggered when the event is
raised:

	Resque_Event::listen('eventName', [callback]);

`[callback]` may be anything in PHP that is callable by `call_user_func_array`:

* A string with the name of a function
* An array containing an object and method to call
* An array containing an object and a static method to call
* A closure (PHP 5.3)

Events may pass arguments (documented below), so your callback should accept
these arguments.

You can stop listening to an event by calling `Resque_Event::stopListening`
with the same arguments supplied to `Resque_Event::listen`.

It is up to your application to register event listeners. When enqueuing events
in your application, it should be as easy as making sure php-resque is loaded
and calling `Resque_Event::listen`.

When running workers, if you run workers via the default `resque.php` script,
your `APP_INCLUDE` script should initialize and register any listeners required
for operation. If you have rolled your own worker manager, then it is again your
responsibility to register listeners.

A sample plugin is included in the `extras` directory.

### Events ###

#### beforeFirstFork ####

Called once, as a worker initializes. Argument passed is the instance of `Resque_Worker`
that was just initialized.

#### beforeFork ####

Called before php-resque forks to run a job. Argument passed contains the instance of
`Resque_Job` for the job about to be run.

`beforeFork` is triggered in the **parent** process. Any changes made will be permanent
for as long as the worker lives.

#### afterFork ####

Called after php-resque forks to run a job (but before the job is run). Argument
passed contains the instance of `Resque_Job` for the job about to be run.

`afterFork` is triggered in the child process after forking out to complete a job. Any
changes made will only live as long as the job is being processed.

#### beforePerform ####

Called before the `setUp` and `perform` methods on a job are run. Argument passed
contains the instance of `Resque_Job` about for the job about to be run.

You can prevent execution of the job by throwing an exception of `Resque_Job_DontPerform`.
Any other exceptions thrown will be treated as if they were thrown in a job, causing the
job to fail.

#### afterPerform ####

Called after the `perform` and `tearDown` methods on a job are run. Argument passed
contains the instance of `Resque_Job` that was just run.

Any exceptions thrown will be treated as if they were thrown in a job, causing the job
to be marked as having failed.

#### onFailure ####

Called whenever a job fails. Arguments passed (in this order) include:

* Exception - The exception that was thrown when the job failed
* Resque_Job - The job that failed

#### afterEnqueue ####

Called after a job has been queued using the `Resque::enqueue` method. Arguments passed
(in this order) include:

* Class - string containing the name of scheduled job
* Arguments - array of arguments supplied to the job
* Queue - string containing the name of the queue the job was added to

## Contributors ##

* chrisboulton
* thedotedge
* hobodave
* scraton
* KevBurnsJr
* jmathai
* dceballos
* patrickbajao
* andrewjshults
* warezthebeef
* d11wtq
* hlegius
* salimane
* humancopy
* pedroarnal
* chaitanyakuber
* maetl
* Matt Heath
* jjfrey
* scragg0x