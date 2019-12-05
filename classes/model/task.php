<?php

namespace Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Task extends \Orm\Model
{
    protected static $_properties = array(
        'id',
        'task',
        'args',
        'state',
        'error_message' => ['default' => null],
        'uuid',
        'priority',
        'user_id',
        'created_at',
        'started_at',
        'updated_at',
    );

    protected static $_observers = array(
        'Orm\Observer_CreatedAt' => array(
            'events' => array('before_insert'),
            'mysql_timestamp' => false,
        ),
        'Orm\Observer_UpdatedAt' => array(
            'events' => array('before_update'),
            'mysql_timestamp' => false,
        ),
    );

    public static $_table_name = 'queue_tasks';

    private static $rabbitmq = null;

    public static function getRabbitChannel()
    {
        if (self::$rabbitmq == null) {
            $config = \Config::get('queue');
            $connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password'], $config['vhost']);
            $channel = $connection->channel();

            $channel->queue_declare(\Queue\Worker::getQueue(), false, true, false, false, false, ['x-max-priority' => ['I', 10]]);

            self::$rabbitmq = $channel;
        }

        return self::$rabbitmq;
    }

    /**
     * Enqueue a task as a message on rabbitmq
     * @param callable $task The callback to execute on the worker
     * @param array $args Arguments to pass to the callback
     * @param int $priority Priority of message in rabbitmq
     * @return int Primary key of the new \Queue\Task
     */
    public static function enqueue($task, $args = [], $priority = null)
    {
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $body = json_encode(array(
            'task' => $task,
            'args' => $args,
            'uuid' => $uuid,
        ));

        $taskqueue = self::forge();
        $taskqueue->task = $task;
        $taskqueue->args = json_encode($args);
        $taskqueue->state = 'pending';
        $taskqueue->user_id = \Auth::get('id', -1);
        $taskqueue->uuid = $uuid;
        if ($priority !== null) {
            $taskqueue->priority = $priority;
        }

        $taskqueue->save();

        if (in_array(\Fuel::$env, \Config::get('queue.autorun', \Config::get('autorun', [\Fuel::DEVELOPMENT])))) {
            call_user_func_array($task, $args);
            return $taskqueue->id;
        }

        $rabbitmq = self::getRabbitChannel();

        $params = array(
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        );
        if ($taskqueue->priority != null) {
            $params['priority'] = $taskqueue->priority;
        }
        $msg = new AMQPMessage($body, $params);
        $rabbitmq->basic_publish($msg, '', \Queue\Worker::getQueue());
        return $taskqueue->id;
    }
}
