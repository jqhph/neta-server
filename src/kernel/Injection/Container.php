<?php
namespace NetaServer\Injection;

use \NetaServer\Exceptions\InternalServerError;
use \NetaServer\Contracts\Container\ContainerInterface;

/**
 * 服务容器
 * */
class Container implements ContainerInterface
{
	# 加载器
	protected $loader;
	
	# 储存临时数据 
	protected $storage;
	
	# 对象池
	protected static $instances;
	
	private function __construct() 
	{
		self::$instances[__CLASS__] = self::$instances['container'] = $this;
		
		$this->loader = new Loader($this);
	}
	
	
	/**
	 * 存取数据
	 * 当$value的值是"null"时为取数据
	 *  
	 * @param string $key
	 * @param string|array $value, 值是null为取数据
	 * @param string|array $default 取不到数据时指定默认值
	 * @return mixed
	 * */
	public function store($key, $value = null, $default = null)
	{
		if ($value === null) {
			return isset($this->storage[$key]) ? $this->storage[$key] : $default;
		}
		
		$this->storage[$key] = $value;
		
		return $value;
	}
	
	public function unstore($key)
	{
		unset($this->storage[$key]);
	}
	
	
	/**
	 * 获取一个服务
	 * 
	 * @param string $abstract 服务类名或别名
	 * @return instance
	 * */
	public function make($abstract)
	{
		if (! isset(self::$instances[$abstract])) {
			 $instance = $this->loader->load($abstract);
			 if (! $instance) {
			 	return $this->build($abstract);
			 }
			 self::$instances[get_class($instance)] = self::$instances[$abstract] = $instance;
		}
		return self::$instances[$abstract];
	}
	
	/**
	 * 注入服务对象到容器
	 * 
	 * @param string $abstract 别名|类名
	 * @param object $instance 对象
	 * @return void
	 * */
	public function instance($abstract, $instance)
	{
		if (! $abstract) {
			throw new InternalServerError('名称为空，注入容器失败');
		}
		if (! is_object($instance)) {
			throw new InternalServerError('instance不是一个类实例，注入容器失败');
		}
		self::$instances[$abstract] = $instance;
	}
	
	/**
	 * Instantiate a concrete instance of the given type.
	 *
	 * @param  string  $concrete
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function build($concrete)
	{
		// If the concrete type is actually a Closure, we will just execute it and
		// hand back the results of the functions, which allows functions to be
		// used as resolvers for more fine-tuned resolution of these objects.
		if ($concrete instanceof \Closure) {
			return $concrete($this);
		}
	
		$reflector = new \ReflectionClass($concrete);
	
		// If the type is not instantiable, the developer is attempting to resolve
		// an abstract type such as an Interface of Abstract Class and there is
		// no binding registered for the abstractions so we need to bail out.
		if (! $reflector->isInstantiable()) {
			if (! empty($this->buildStack)) {
				$previous = implode(', ', $this->buildStack);
	
				$message = "Target [$concrete] is not instantiable while building [$previous].";
			} else {
				$message = "Target [$concrete] is not instantiable.";
			}
	
			throw new InternalServerError($message);
		}
	
		$this->buildStack[] = $concrete;
	
		$constructor = $reflector->getConstructor();
	
		// If there are no constructors, that means there are no dependencies then
		// we can just resolve the instances of the objects right away, without
		// resolving any other types or dependencies out of these containers.
		if (is_null($constructor)) {
			array_pop($this->buildStack);
	
			return new $concrete;
		}
	
		$dependencies = $constructor->getParameters();
	
		$instances = $this->getDependencies($dependencies);
	
		array_pop($this->buildStack);
	
		return $reflector->newInstanceArgs($instances);
	}
	
	/**
	 * Resolve all of the dependencies from the ReflectionParameters.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getDependencies(array $parameters)
	{
		$dependencies = [];
	
		foreach ($parameters as $parameter) {
			$dependency = $parameter->getClass();
	
			// If the class is null, it means the dependency is a string or some other
			// primitive type which we can not resolve since it is not a class and
			// we will just bomb out with an error since we have no-where to go.
			$dependencies[] = $this->resolveClass($parameter);
		}
	
		return $dependencies;
	}
	
	/**
	 * Resolve a class based dependency from the container.
	 *
	 * @param  \ReflectionParameter  $parameter
	 * @return mixed
	 *
	 * @throws \Illuminate\Contracts\Container\BindingResolutionException
	 */
	protected function resolveClass(\ReflectionParameter $parameter)
	{
		return $this->make($parameter->getClass()->name);
	}
	
	/**
	 * 注册一个服务
	 * 
	 * @param string|array $abstract 类名 或 类名=>别名
 	 * @param  \Closure|string|null  $concrete
     * @param  bool  $shared 是否单例
     * @return void
	 * */
// 	public function bind($abstract, $concrete, $dependencies = '')
// 	{
//  		$this->loader->regist($abstract, $concrete, $dependencies);
//  		return $this;
// 	}
	
	
	
	/**
	 * Drop all of the stale instances and aliases.
	 *
	 * @param  string  $abstract
	 * @return void
	 */
	protected function dropStaleInstances($abstract)
	{
		$class = get_class(self::$instances[$abstract]);
		unset(self::$instances[$class]);
		unset(self::$instances[$abstract], $this->aliases[$abstract]);
	}
	
	public function clear($except = [])
	{
		$new = [];
		foreach ((array) $except as & $e) {
			if (isset(self::$instances[$e])) {
				$new[$e] = self::$instances[$e];
			}
		}
		self::$instances = null;
		self::$instances = & $new;
	}
	
	/**
	 * 检测服务是否已注入容器
	 * 
	 * @return bool
	 * */
	public function exist($abstract)
	{
		return isset(self::$instances[$abstract]);
	}
	
	/**
	 * 获取所有已注入容器服务的名称
	 * 
	 * @return array
	 * */
	public function getInstancesKeys() 
	{
		return array_keys(self::$instances);
	}
	
    public static function getInstance()
    {
    	return isset(self::$instances['container']) ? self::$instances['container'] : new static();
    }
	
	/**
	 * 获取标准的类名
	 * */
// 	public function normalize($service)
// 	{
// 		return is_string($service) ? ltrim($service, '\\') : $service;
// 	}
	
	
	private function __clone() 
	{
		
	}
}
