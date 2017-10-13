<?php

namespace Fuel\Migrations;

class add_priority_to_tasks
{
    public function up()
    {
        \DBUtil::add_fields('queue_tasks', array(
            'priority' => array('type' => 'tinyint', 'after' => 'uuid', 'null' => true, 'default' => null, 'unsigned' => true),
        ));
    }

    public function down()
    {
        \DBUtil::drop_fields('queue_tasks', array(
            'priority'
        ));
    }
}
