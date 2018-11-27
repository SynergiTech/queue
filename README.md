# fuelphp-queue

> An abstraction over RabbitMQ for use in FuelPHP.

## Usage

```php
class MyTask
{
  public static function sum(...$args)
  {
    return array_sum($args);
  }
}

Queue\Task::enqueue([MyTask::class, 'multiply'], [2, 4], 3); # 6
```

## Configuration

* `autorun` _(array)_ a list of environments where the job is executed immediately, rather
  than enqueuing a job in RabbitMQ. Defaults to `['development']`, which means that
  **by default, jobs will immediately execute in development**.

* `queue.host` _(string)_ the host where your RabbitMQ instance can be found, defaults to `'127.0.0.1'`
* `queue.port` _(int)_ the port where your RabbitMQ instance can be found, defaults to `5672`
* `queue.user` _(string)_ the RabbitMQ username, defaults to `'guest'`
* `queue.password` _(string)_ the RabbitMQ password, defaults to `'guest'`
* `queue.vhost` _(string)_ the RabbitMQ virtual host, defaults to `'/'`
