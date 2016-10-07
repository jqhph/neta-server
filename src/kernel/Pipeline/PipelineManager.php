<?php
namespace JQH\Pipeline;

use JQH\Basis\Factory;

class PipelineManager extends Factory
{
	protected $defaultName = 'JQH';
	
	public function create($name)
	{
		return new \JQH\Pipeline\Pipeline($this->container);
	}
}
