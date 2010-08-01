## 1.1 (????-??-??) ##
* Change arguments for jobs to be an array as they're easier to work with in
PHP.
* Implement ability to have setUp and tearDown methods for jobs, called before
and after every single run.
* Ability to specify a cluster/multiple redis servers and consistent hash
between them (Thanks dceballos)
* Fix `APP_INCLUDE` environment variable not loading correctly.
* Jobs are no longer defined as static methods, and classes are instantiated
first. This change is NOT backwards compatible and requires job classes are
updated.
* Job arguments are passed to the job class when it is instantiated, and
are accessible by $this->args. This change will break existing job classes
that rely on arguments that have not been updated.

## 1.0 (2010-04-18) ##

* Initial release