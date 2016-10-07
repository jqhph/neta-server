<?php
namespace NetaServer\Utils\Debug;

use \NetaServer\Injection\Container;
use \NetaServer\Support\Arr;

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
