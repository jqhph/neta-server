<?php
namespace NetaServer\Pipeline;

use \NetaServer\Injection\Container;
use \Closure;

class Pipeline implements \NetaServer\Contracts\Pipeline\PipelineInterface
{
	/**
	 * The container implementation.
	 *
	 * @var \Illuminate\Contracts\Container\Container
	 */
	protected $container;

	/**
	 * The object being passed through the pipeline.
	 *
	 * @var mixed
	 */
	protected $passable;

	/**
	 * The array of class pipes.
	 *
	 * @var array
	 */
	protected $pipes = [];

	/**
	 * The method to call on each pipe.
	 *
	 * @var string
	 */
	protected $method = 'handle';

	/**
	 * Create a new class instance.
	 *
	 * @param  \Illuminate\Contracts\Container\Container  $container
	 * @return void
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Set the argument being sent through the pipeline.
	 *
	 * @param  mixed  $passable
	 * @return $this
	 */
	public function send($passable)
	{
		$this->passable = $passable;

		return $this;
	}

	/**
	 * Set the array of pipes.
	 *
	 * @param  array|mixed  $pipes
	 * @return $this
	 */
	public function through($pipes)
	{
		$this->pipes = (array) $pipes;

		return $this;
	}

	/**
	 * Set the method to call on the pipes.
	 *
	 * @param  string  $method
	 * @return $this
	 */
	public function via($method)
	{
		$this->method = $method;

		return $this;
	}

	/**
	 * Run the pipeline with a final destination callback.
	 *
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	public function then(Closure $destination)
	{
		//$firstSlice = $this->getInitialSlice($destination);
		$pipes = array_reverse($this->pipes);

		return call_user_func(array_reduce($pipes, [$this, 'getSlice'], $destination), $this->passable);
	}

	/**
	 * Get a Closure that represents a slice of the application onion.
	 *
	 * @return \Closure
	 */
	protected function getSlice($stack, $pipe)
	{
		return function ($passable) use ($stack, $pipe) {
			$this->normalize($pipe);
			return call_user_func($pipe, $passable, $stack);
		};
	}
	
	/**
	 * Get callable pipe.
	 * 
	 * @param string|object $pipe 
	 * @return array
	 * */
	protected function normalize(& $pipe)
	{
		if ($pipe instanceof Closure) {
			
		} elseif (is_string($pipe)) {
			$pipe = [$this->container->make($pipe), $this->method];
			
		} else {
			$pipe = [$pipe, $this->method];
			
		}
		
// 		elseif (is_string($pipe[0])) {
// 			$pipe[0] = $this->container->make($pipe[0]);
				
// 		}
	}

	/**
	 * Get the initial slice to begin the stack call.
	 *
	 * @param  \Closure  $destination
	 * @return \Closure
	 */
	protected function getInitialSlice(Closure $destination)
	{
		return function ($passable) use ($destination) {
			return call_user_func($destination, $passable);
		};
	}

	/**
	 * Parse full pipe string to get name and parameters.
	 *
	 * @param  string $pipe
	 * @return array
	 */
// 	protected function parsePipeString($pipe)
// 	{
// 		list($name, $parameters) = array_pad(explode(':', $pipe, 2), 2, []);

// 		if (is_string($parameters)) {
// 			$parameters = explode(',', $parameters);
// 		}

// 		return [$name, $parameters];
// 	}
}
