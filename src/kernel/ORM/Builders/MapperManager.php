<?php
namespace NetaServer\ORM\Builders;

class MapperManager extends \NetaServer\Basis\Factory
{
	protected $defaultName = 'SQLJ';
	/**
	 * 创建一个映射器
	 * */
	public function create($name) 
	{
		$class = '\\NetaServer\\ORM\\Builders\\' . $name . '\\Mapper';

		return new $class($this->container);
	}
	
	protected function pdo() 
	{
		return $this->getContainer()->get('pdo');
	}
	
}
