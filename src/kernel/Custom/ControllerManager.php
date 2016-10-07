<?php
namespace JQH\Custom;

use \JQH\Basis\Pool;
use \JQH\Exceptions\NotFound;

/**
 * 控制器管理类
 * */
class ControllerManager extends \JQH\Basis\Factory
{
	/**
	 * 生产一个控制器对象
	 * */
	protected function create($name)
	{
		$className = '\\App\\' . __MODULE__ . '\\Contr\\' . $name;
		
		if (! class_exists($className)) {
			$className = '\\Custom\\' . __MODULE__ . '\\Contr\\' . $name;
		}

		if (class_exists($className)) {
			$instance = new $className();
			$instance->setContainer($this->container);
			$instance->setControllerName($name);
			
			return $instance;
		} else {
			throw new NotFound('Controller[' . $name . '] not exists.');
		}
	}
	
}
