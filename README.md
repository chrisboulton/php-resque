Php-Resque-ex: Resque for PHP [![Build Status](https://secure.travis-ci.org/chrisboulton/php-resque.png)](http://travis-ci.org/chrisboulton/php-resque)
===========================================

Resque is a Redis-backed library for creating background jobs, placing
those jobs on multiple queues, and processing them later.

## Background ##

Php-Resque-Ex is a fork of [php-resque](https://github.com/chrisboulton/php-resque) by chrisboulton. See the [original README](https://github.com/chrisboulton/php-resque/blob/master/README.md) for more informations.

## Additional features ##

This fork provides some additional features :

### Support of php-redis

Autodetect and use [phpredis](https://github.com/nicolasff/phpredis) to connect to Redis if available. Redisent is used as fallback.

### Powerfull logging

Instead of piping STDOUT output to a file, you can log directly to a database, or send them elsewhere via a socket. We use [Monolog](https://github.com/Seldaek/monolog) to manage all the logging. See their documentation to see all the available handlers.

Log infos are augmented with more informations, and associated with a workers, a queue, and a job ID if any.

### Failed jobs logs

You can easily retrieve logs for a failed jobs in the redis database, their keys are named after their job ID. Each failed log will expire after 2 weeks to save space.

### Command Line tool

Fresque is shipped by default to manage your workers. See [Fresque Documentation](https://github.com/kamisama/Fresque) for usage.

## Requirements ##

* PHP 5.3+
* Redis 2.2+

## Contributors ##

* [chrisboulton](https://github.com/chrisboulton/php-resque) for the original port
* kamisama
