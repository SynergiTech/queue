<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.8
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2016 Fuel Development Team
 * @link       http://fuelphp.com
 */

\Autoloader::add_core_namespace('Queue');

\Autoloader::add_classes(array(
    'Queue\\Worker' => __DIR__.'/classes/worker.php',

    'Queue\\Task' => __DIR__.'/classes/model/task.php',
));
