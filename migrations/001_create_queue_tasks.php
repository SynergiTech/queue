<?php

namespace Fuel\Migrations;

class Create_queue_tasks
{
	public function up()
	{
		\DBUtil::create_table('queue_tasks', array(
			'id' => array('constraint' => 11, 'type' => 'int', 'auto_increment' => true, 'unsigned' => true),
			'task' => array('constraint' => 255, 'type' => 'varchar'),
			'args' => array('type' => 'text'),
			'state' => array('constraint' => '"pending","running","success","error","cancelled"', 'type' => 'enum'),
			'uuid' => array('constraint' => 36, 'type' => 'varchar'),
			'user_id' => array('constraint' => 11, 'type' => 'int'),
			'created_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),
			'updated_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),

		), array('id'));
	}

	public function down()
	{
		\DBUtil::drop_table('queue_tasks');
	}
}