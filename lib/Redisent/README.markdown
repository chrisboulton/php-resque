# Redisent

Redisent is a simple, no-nonsense interface to the [Redis](http://code.google.com/p/redis/) key-value store for modest developers.
Due to the way it is implemented, it is flexible and tolerant of changes to the Redis protocol.

## Getting to work

If you're at all familiar with the Redis protocol and PHP objects, you've already mastered Redisent.
All Redisent does is map the Redis protocol to a PHP object, abstract away the nitty-gritty, and make the return values PHP compatible.

    require 'redisent.php';
    $redis = new Redisent('localhost');
    $redis->set('awesome', 'absolutely');
    echo sprintf('Is Redisent awesome? %s.\n', $redis->get('awesome'));

You use the exact same command names, and the exact same argument order. **How wonderful.** How about a more complex example?

    require 'redisent.php';
    $redis = new Redisent('localhost');
    $redis->rpush('particles', 'proton');
    $redis->rpush('particles', 'electron');
    $redis->rpush('particles', 'neutron');
    $particles = $redis->lrange('particles', 0, -1);
    $particle_count = $redis->llen('particles');
    echo "<p>The {$particle_count} particles that make up atoms are:</p>";
    echo "<ul>";
    foreach ($particles as $particle) {
      echo "<li>{$particle}</li>";
    }
    echo "</ul>";

Be aware that Redis error responses will be wrapped in a RedisException class and thrown, so do be sure to use proper coding techniques.

## Clustering your servers

Redisent also includes a way for developers to fully utilize the scalability of Redis with multiple servers and [consistent hashing](http://en.wikipedia.org/wiki/Consistent_hashing).
Using the RedisentCluster class, you can use Redisent the same way, except that keys will be hashed across multiple servers.
Here is how to set up a cluster:

    include 'redisent_cluster.php';

    $cluster = new RedisentCluster(array(
	  array('host' => '127.0.0.1', 'port' => 6379),
	  array('host' => '127.0.0.1', 'port' => 6380)
    ));

You can then use Redisent the way you normally would, i.e., `$cluster->set('key', 'value')` or `$cluster->lrange('particles', 0, -1)`.
But what about when you need to use commands that are server specific and do not operate on keys? You can use routing, with the `RedisentCluster::to` method.
To use routing, you need to assign a server an alias in the constructor of the Redis cluster. Aliases are not required on all servers, just the ones you want to be able to access directly.

    include 'redisent_cluster.php';

    $cluster = new RedisentCluster(array(
	  'alpha' => array('host' => '127.0.0.1', 'port' => 6379),
	  array('host' => '127.0.0.1', 'port' => 6380)
    ));

Now there is an alias of the server running on 127.0.0.1:6379 called **alpha**, and can be interacted with like this:

    // get server info
    $cluster->to('alpha')->info();

Now you have complete programatic control over your Redis servers.

## About

&copy; 2009 [Justin Poliey](http://justinpoliey.com)