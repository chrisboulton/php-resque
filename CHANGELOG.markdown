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