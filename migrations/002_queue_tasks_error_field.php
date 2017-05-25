<?php

namespace Fuel\Migrations;

class queue_tasks_error_field
{
    public function up()
    {
        \DBUtil::add_fields('queue_tasks', array(
            'error_message' => array('type' => 'text', 'after' => 'state', 'null' => true, 'default' => null),
            'started_at' => array('type' => 'int', 'after' => 'created_at', 'null' => true, 'default' => null),
        ));
    }

    public function down()
    {
        \DBUtil::drop_fields('queue_tasks', array(
            'error_message', 'started_at'
        ));
    }
}
