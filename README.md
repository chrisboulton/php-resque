# php-resque

**php-resque is a Redis-based library for enqueuing and running background jobs.**

This is a lightly maintained fork of **chrisboulton/php-resque**, with fixes and improvements, compatible with PHP 8.2+.

⚠️ Not recommended for new projects. We are only maintaining this for legacy projects.

## Installation

Add the package with composer:

```bash
composer require softwarepunt/php-resque
```

## Usage

### Configuration

If you are not using default Redis configuration, set the backend manually:

```php
Resque::setBackend('localhost:6379');
```

### Define jobs

Each job should be in its own class, and include a perform method:

```php
namespace MyApp;

class Greeter_Job
{
    public function setUp()
    {
        // Optional: Set up before (called before perform())
    }

    public function perform()
    {
        // Required: Main work method
        
        // Perform work; context data is accessible from $this->args
        echo "Hello, {$this->args['name']}!";
    }
    
    public function tearDown()
    {
        // Optional: Tear down after (called after job finishes)
    }
}
```

### Enqueue jobs

Jobs instances are placed in specific queues with a set of context data (args):

```php
// Enqueue an instance of "My_Job" in the "default" queue
Resque::enqueue('default', 'MyApp\Greeter_Job', ['name' => "Hank"]);
```

### Workers

Start a worker with the `QUEUE` environment variable to begin processing jobs from that queue:

```php
QUEUE=default php vendor/bin/resque
```

#### Environment variables

You can set the following environment variables on the worker:

| Name          | Description                                                                                                                                                                                                          |
|---------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `QUEUE`       | Required. Defines one or more comma-separated queues to process tasks from. Set to `*` to process from any queue.                                                                                                    |
| `APP_INCLUDE` | Optional. Defines a bootstrap script to run before starting the worker.                                                                                                                                              |
| `PREFIX`      | Optional. Prefix to use in Redis.                                                                                                                                                                                    |
| `COUNT`       | Optional. Amount of worker forks to start. If set to > 1, the process will start the workers and then exit immediately.                                                                                              |
| `SCHEDULE`    | Optional. An expression with a from/until time, e.g. `22:00-06:00` to only run tasks between 10pm and 6am. The worker is paused outside of the schedule. Relative to default timezone (`date_default_timezone_set`). | 
| `VERBOSE`     | Optional. Forces verbose log output.                                                                                                                                                                                 |
| `VVERBOSE`    | Optional. Forces detailed verbose log output.                                                                                                                                                                        |

### Events

You can listen for specific events to track status changes:

```php
Resque_Event::listen('eventName', $callback);
```

`$callback` may be anything in PHP that is callable by `call_user_func_array`.

| Name              | Description                                                             | Event arguments                                          |
|-------------------|-------------------------------------------------------------------------|----------------------------------------------------------|
| `beforeFirstFork` | Called once, as a worker initializes.                                   | `Resque_Worker $worker`                                  |
| `beforeFork`      | Called before php-resque forks to run a job.                            | `Resque_Job $job`                                        |
| `afterFork`       | Called after php-resque forks to run a job (but before the job is run). | `Resque_Job $job`                                        |
| `beforePerform`   | Called once per job run, before `setUp` and `perform`.                  | `Resque_Job $job`                                        |
| `afterPerform`    | Called once per job run, after `perform` and `tearDown`.                | `Resque_Job $job`                                        |
| `onFailure`       | Called whenever a job fails.                                            | `Exception $ex, Resque_Job $job`                         |
| `beforeEnqueue`   | Called before a job has been queued using `enqueue`.                    | `string $class, ?array $args, string $queue, string $id` |
| `afterEnqueue`    | Called after a job has been queued using `enqueue`.                     | `string $class, ?array $args, string $queue, string $id` |
