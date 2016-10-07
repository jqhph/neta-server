<?php
namespace JQH\Custom;

use \JQH\Basis\Factory;

/**
 * model工厂类
 * */
class ModelFactory extends Factory
{
	protected function create($name) 
	{
		$className = '\\App\\' . __MODULE__ . '\\Model\\' . $name;
		
		if (! class_exists($className)) {
			$className = '\\Custom\\' . __MODULE__ . '\\Model\\' . $name;
		}
		
		if ($default = C('default-model')) {
			if (! class_exists($className)) {
				$className = $default;
			}
		} else {
			if (! class_exists($className)) {
				$className = '\\JQH\\Basis\\Model\\Base';
			}
		}
		
		return new $className($name, $this->container);
	}
	
}
