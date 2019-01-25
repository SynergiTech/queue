<?php

namespace Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class Worker
{
    private $config;
    private $connection = null;
    private $channel = null;

    public function __construct($config = [])
    {
        if (method_exists('\\Orm\\Query', 'caching')) {
            \Orm\Query::caching(false);
        }

        $config = array_merge_recursive(\Config::get('queue'), $config);

        $this->config = $config;
    }

    public function start()
    {
        $this->configureConsumer();
    }

    public function run()
    {
        if ($this->connection == null) {
            return;
        }
        $read = [$this->connection->getSocket()];
        $write = null;
        $except = null;

        if (($changeStreamsCount = stream_select($read, $write, $except, 0)) > 0) {
            $this->channel->wait();
        }
    }

    public function message($msg)
    {
        $payload = json_decode($msg->body, true);
        $task = \Queue\Task::query()
            ->where('uuid', $payload['uuid'])
            ->where('state', 'pending')
            ->get_one();

        $result = $this->execute($task);

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

    public function restartDB()
    {
        foreach (\Config::get('db') as $db => $_) {
            try {
                \DB::instance()->disconnect($db);
                \DB::instance()->connect($db);
            } catch (\Database_Exception $e) {
            }
        }
    }

    private function execute($task)
    {
        if (!($task instanceof \Queue\Task) or $task->state !== 'pending') {
            return false;
        }

        $return = false;
        try {
            $was_updated = \DB::update(\Queue\Task::$_table_name)
                ->value('state', 'running')
                ->value('started_at', time())
                ->where('id', $task->id)
                ->where('state', 'pending')
                ->execute();

            if ($was_updated == 0) {
                \Log::info("Did not start task ".$task->uuid." due to possible conflict with another worker.");
                return false;
            }

            $args = json_decode($task->args);

            $this->event('pre-execute', [$task]);
            $this->restartDB();

            $execution_start_time = microtime(true);
            $result = call_user_func_array($task->task, $args);
            $duration = microtime(true) - $execution_start_time;

            $this->restartDB();
            $this->event('post-execute', [$task, $result, $duration]);

            $task->state = 'success';
            $return = true;
        } catch (\Throwable $e) {
            $task->state = 'error';
            $task->error_message = $e->getMessage();
            $this->event('error', [$task, $e]);
            \Log::error($e->getMessage(), array('exception' => $e));
        } catch (\Exception $e) {
            $task->state = 'error';
            $task->error_message = $e->getMessage();
            $this->event('error', [$task, $e]);
            \Log::error($e->getMessage(), array('exception' => $e));
        } finally {
            $this->restartDB();
            $task->save();
        }

        return $return;
    }

    public function stop()
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }

    public static function getQueue()
    {
        return \Config::get('queue.queue').'-'.\Fuel::$env;
    }

    public function configureConsumer()
    {
        $connection = new AMQPStreamConnection($this->config['host'], $this->config['port'], $this->config['user'], $this->config['password'], $this->config['vhost']);
        $channel = $connection->channel();
        $channel->basic_qos(null, 1, null);

        if ($this->config['callback'] == null or !is_callable($this->config['callback'])) {
            $callback = array($this, 'message');
        } else {
            $callback = $this->config['callback'];
        }

        $channel->queue_declare(self::getQueue(), false, true, false, false, false, ['x-max-priority' => ['I', 10]]);
        $channel->basic_consume(self::getQueue(), '', false, false, false, false, $callback);

        $this->connection = $connection;
        $this->channel = $channel;
    }

    public function event($name, $data = [])
    {
        $n = 0;
        if (isset($this->config['events'][$name]) and is_array($this->config['events'][$name])) {
            foreach ($this->config['events'][$name] as $cb) {
                if (is_callable($cb)) {
                    call_user_func_array($cb, $data);
                }
            }
        }

        return $n;
    }
}
