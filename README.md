Php-Resque-ex: Resque for PHP [![Build Status](https://secure.travis-ci.org/kamisama/php-resque-ex.png)](http://travis-ci.org/kamisama/php-resque-ex)
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

## Installation

Clone the git repo

	$ git clone git://github.com/kamisama/php-resque-ex.git
	
 `cd` into the folder you just cloned

	$ cd ./php-resque-ex
	
Download Composer

	$ curl -s https://getcomposer.org/installer | php
	
Install dependencies

	$ php composer.phar install
	
## Usage

Use the same way as the original port, with two additional ENV :

* `LOGHANDLER` : Specify the handler to use for logging (File, MongoDB, Socket, etc …).  
 See [Monolog](https://github.com/Seldaek/monolog#handlers) doc for all available handlers.
`LOGHANDLER` is the name of the handler, without the "Handler" part. To use CubeHandler, just type "Cube".
* `LOGHANDLERTARGET` : Information used by the handler to connect to the database.  
Depends on the type of loghandler. If it's the *FileRotateHandler*, the target will be the filename. If it's CubeHandler, target will be a udp address. Refer to each Handler to see what type of argument their `__construct()` method requires.

If one of these two environement variable is missing, it will default to *FileRotating* Handler.

A symlink to **Fresque** is created in the `bin` folder, to manage your workers.

## Requirements ##

* PHP 5.3+
* Redis 2.2+

## Contributors ##

* [chrisboulton](https://github.com/chrisboulton/php-resque) for the original port
* kamisama
