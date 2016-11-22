<?php
namespace NetaServer\ORM\Builders;

class BuilderManager extends \NetaServer\Basis\Factory
{
	protected $defaultName = 'SQLJ';
	/**
	 * 创建一个映射器
	 * */
	public function create($name) 
	{
	    $name = $name ?: $this->defaultName;
	    
		$class = '\\NetaServer\\ORM\\Builders\\' . $name . '\\Builder';

		return new $class($this->container);
	}
	
}
