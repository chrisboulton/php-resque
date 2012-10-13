## 1.2 (2012-10-13) ##

**Note:** This release is largely backwards compatible with php-resque 1.1. The next release will introduce backwards incompatible changes (moving from Redisent to Credis), and will drop compatibility with PHP 5.2.

* Allow alternate redis database to be selected when calling setBackend by supplying a second argument (patrickbajao)
* Use `require_once` when including php-resque after the app has been included in the sample resque.php to prevent include conflicts (andrewjshults)
* Wrap job arguments in an array to improve compatibility with ruby resque (warezthebeef)
* Fix a bug where the worker would spin out of control taking the server with it, if the redis connection was interrupted even briefly. Use SIGPIPE to trap this scenario cleanly. (d11wtq)
* Added support of Redis prefix (namespaces) (hlegius)
* When reserving jobs, check if the payload received from popping a queue is a valid object (fix bug whereby jobs are reserved based on an erroneous payload) (salimane)
* Re-enable autoload for class_exists in Job.php (humancopy)
* Fix lost jobs when there is more than one worker process started by the same parent process (salimane)
* Move include for resque before APP_INCLUDE is loaded in, so that way resque is available for the app
* Avoid working with dirty worker IDs (salimane)
* Allow UNIX socket to be passed to Resque when connecting to Redis (pedroarnal)
* Fix typographical errors in PHP docblocks (chaitanyakuber)
* Set the queue name on job instances when jobs are executed (chaitanyakuber)
* Fix and add tests for Resque_Event::stopListening (ebernhardson)
* Documentation cleanup (maetl)
* Pass queue name to afterEvent callback
* Only declare RedisException if it doesn't already exist (Matt Heath)
* Add support for Composer
* Fix missing and incorrect paths for Resque and Resque_Job_Status classes in demo (jjfrey)
* Disable autoload for the RedisException class_exists call (scragg0x)
* General tidyup of comments and files/folders

## 1.1 (2011-03-27) ##

* Update Redisent library for Redis 2.2 compatibility. Redis 2.2 is now required. (thedotedge)
* Trim output of `ps` to remove any prepended whitespace (KevBurnsJr)
* Use `getenv` instead of `$_ENV` for better portability across PHP configurations (hobodave)
* Add support for sub-second queue check intervals (KevBurnsJr)
* Ability to specify a cluster/multiple redis servers and consistent hash between them (dceballos)
* Change arguments for jobs to be an array as they're easier to work with in PHP.
* Implement ability to have setUp and tearDown methods for jobs, called before and after every single run.
* Fix `APP_INCLUDE` environment variable not loading correctly.
* Jobs are no longer defined as static methods, and classes are instantiated first. This change is NOT backwards compatible and requires job classes are updated.
* Job arguments are passed to the job class when it is instantiated, and are accessible by $this->args. This change will break existing job classes that rely on arguments that have not been updated.
* Bundle sample script for managing php-resque instances using monit
* Fix undefined variable `$child` when exiting on non-forking operating systems
* Add `PIDFILE` environment variable to write out a PID for single running workers

## 1.0 (2010-04-18) ##

* Initial release