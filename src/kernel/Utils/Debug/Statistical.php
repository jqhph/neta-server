<?php
namespace JQH\Utils\Debug;

use \JQH\Injection\Container;
use \JQH\Support\Arr;

class Statistical
{
	protected $container;
	
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	public function run()
	{

	}

	protected function getConfig()
	{
		return $this->container->get('config');
	}
	
}
