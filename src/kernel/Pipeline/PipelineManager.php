<?php
namespace NetaServer\Pipeline;

use NetaServer\Basis\Factory;

class PipelineManager extends Factory
{
	protected $defaultName = 'JQH';
	
	public function create($name)
	{
		return new \NetaServer\Pipeline\Pipeline($this->container);
	}
}
