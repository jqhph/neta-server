<?php
namespace Neta\ORM\Builders;

class MapperManager extends \Neta\Basis\Factory
{
	protected $defaultName = 'SQLJ';
	/**
	 * 创建一个映射器
	 * */
	public function create($name) 
	{
		$class = '\\Neta\\ORM\\Builders\\' . $name . '\\Mapper';

		return new $class($this->container);
	}
	
	protected function pdo() 
	{
		return $this->getContainer()->get('pdo');
	}
	
}
