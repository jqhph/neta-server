<?php
namespace NetaServer\ORM\Mappers;

class MapperManager extends \NetaServer\Basis\Factory
{
	protected $defaultName = 'SQLJ';
	/**
	 * 创建一个映射器
	 * */
	public function create($name) 
	{
		$class = '\\NetaServer\\ORM\\Mappers\\' . $name . '\\Mapper';

		return new $class($this->container);
	}
	
	protected function pdo() 
	{
		return $this->getContainer()->get('pdo');
	}
	
}
