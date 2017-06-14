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
            $connection = new AMQPStreamConnection(\Config::get('host'), \Config::get('port'), \Config::get('user'), \Config::get('password'), \Config::get('vhost'));
            $channel = $connection->channel();

            $channel->queue_declare(\Queue\Worker::getQueue(), false, true, false, false, false, ['x-max-priority' => ['I', 10]]);

            self::$rabbitmq = $channel;
        }

        return self::$rabbitmq;
    }

    public static function enqueue($task, $args = [], $priority = null)
    {
        $queue = \Config::load('queue');
        // task uuid
        $uuid = \Str::random('uuid');

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

        if (false and \Fuel::$env == 'development') {
            call_user_func_array($task, $args);
            return;
        }

        $rabbitmq = self::getRabbitChannel();

        $msg = new AMQPMessage($body, array(
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ));
        return $rabbitmq->basic_publish($msg, '', \Queue\Worker::getQueue());
    }
}
