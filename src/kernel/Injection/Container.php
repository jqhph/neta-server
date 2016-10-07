<?php
namespace NetaServer\Injection;

use \NetaServer\Exceptions\InternalServerError;

/**
 * 服务容器
 * */
class Container implements \NetaServer\Contracts\Container\ContainerInterface
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

		return $this;
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
	 * 获取一个服务, 等同于get方法
	 *
	 * @param string $abstract 服务类名或别名
	 * @return instance
	 * */
	public function make($abstract)
	{
		if (! isset(self::$instances[$abstract])) {
			self::$instances[$abstract] = $this->loader->load($abstract);
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
	 * 注册一个服务
	 *
	 * @param string|array $abstract 类名 或 类名=>别名
	 * @param  \Closure|string|null  $concrete
	 * @param  bool  $shared 是否单例
	 * @return void
	 * */
	public function bind($abstract, $concrete, $dependencies = '')
	{
// 		$this->loader->regist($abstract, $concrete, $dependencies);
// 		return $this;
	}



	/**
	 * Drop all of the stale instances and aliases.
	 *
	 * @param  string  $abstract
	 * @return void
	 */
	protected function dropStaleInstances($abstract)
	{
		unset(self::$instances[$abstract], $this->aliases[$abstract]);
	}

	/**
	 * 检测服务是否已注入容器
	 *
	 * @return bool
	 * */
	public function exists($abstract)
	{
		return isset(self::$instances[$abstract]);
	}

	/**
	 * 获取已注册服务(loader加载)
	 *
	 * @param $alias string 载入的类实例的别名
	 * @return object
	 * */
	public function get($abstract)
	{
		if (! isset(self::$instances[$abstract])) {
			self::$instances[$abstract] = $this->loader->load($abstract);
		}
		return self::$instances[$abstract];
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

	/**
	 * 注入服务到容器
	 * */
	public function set($abstract, $instance)
	{
		if (! $abstract) {
			throw new InternalServerError('名称为空，注入容器失败');
		}
		if (! is_object($instance)) {
			throw new InternalServerError('instance不是一个类实例，注入容器失败');
		}
		self::$instances[$abstract] = $instance;
	}

	public static function getInstance()
	{
		return isset(self::$instances['container']) ? self::$instances['container'] : new static();
	}

	public function clear($except = [])
	{
		$new = [];
		foreach ((array) $except as & $e) {
			if (isset(self::$instances[$e])) {
				$new[$e] = self::$instances[$e];
			}
		}
		self::$instances = $new;
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
