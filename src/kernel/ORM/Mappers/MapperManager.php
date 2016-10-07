<?php
namespace JQH\ORM\Mappers;

class MapperManager extends \JQH\Basis\Factory
{
	protected $defaultName = 'SQLJ';
	/**
	 * 创建一个映射器
	 * */
	public function create($name) 
	{
		$class = '\\JQH\\ORM\\Mappers\\' . $name . '\\Mapper';

		return new $class($this->container);
	}
	
	protected function pdo() 
	{
		return $this->getContainer()->get('pdo');
	}
	
}
