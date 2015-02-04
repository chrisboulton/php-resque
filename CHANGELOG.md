## 1.3 (2013-??-??) - Current Master ##

**Note:** This release introduces backwards incompatible changes with all previous versions of php-resque. Please see below for details.

### Redisent (Redis Library) Replaced with Credis

Redisent has always been the Redis backend for php-resque because of its lightweight nature. Unfortunately, Redisent is largely unmaintained.

[Credis](https://github.com/colinmollenhour/credis) is a fork of Redisent, which among other improvements will automatically use the [phpredis](https://github.com/nicolasff/phpredis) native PHP extension if it is available. (you want this for speed, trust me)

php-resque now utilizes Credis for all Redis based operations. Credis automatically required and installed as a Composer dependency.

### Composer Support

Composer support has been improved and is now the recommended method for including php-resque in your project. Details on Composer support can be found in the Getting Started section of the readme.

### Improved DSN Support

Changes by iskandar introduce improved support for using DSNs to connect to Redis. You can now utilize the following formatted strings for the REDIS_BACKEND environment variable to connect:

* `host`
* `host:port`
* `redis://host:port`
* `redis://host:port/db`
* `redis://user:pass@host:port/` (username is required but will be ignored)
* `tcp://user:pass@host:port/` (username is required but will be ignored)

### Other Improvements/Changes

* **COMPATIBILITY BREAKING**: The bundled worker manager `resque.php` has been moved to `bin/resque`, and is available as `vendor/bin/resque` when php-resque is installed as a Composer package.
* Restructure tests and test bootstrapping. Autoload tests via Composer (install test dependencies with `composer install --dev`)
* Add `SETEX` to list of commands which supply a key as the first argument in Redisent (danhunsaker)
* Fix an issue where a lost connection to Redis could cause an infinite loop (atorres757)
* Add a helper method to `Resque_Redis` to remove the namespace applied to Redis keys (tonypiper)
* Call beforePerform hook before retrieivng an instance of the job class (allows beforePerform to cancel a job with DontPerform before initialising your application)
* Add `beforeEnqueue` hook, called before a job is placed on a queue

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
* General tidy up of comments and files/folders

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
