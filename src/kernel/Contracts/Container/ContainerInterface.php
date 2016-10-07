<?php
namespace NetaServer\Contracts\Container;

use Closure;

interface ContainerInterface
{

	/**
	 * Register a binding with the container.
	 *
	 * @param  string|array  $abstract
	 * @param  \Closure|string|null  $concrete
	 * @param  bool  $shared
	 * @return void
	 */
	public function bind($abstract, $concrete, $dependencies = '');

	/**
	 * Register an existing instance as shared in the container.
	 *
	 * @param  string  $abstract
	 * @param  mixed   $instance
	 * @return void
	 */
	public function instance($abstract, $instance);


	/**
	 * Resolve the given type from the container.
	 *
	 * @param  string  $abstract
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function make($abstract);

	/**
	 * 获取注册服务
	 *
	 * @param  string  $abstract
	 * @return mixed
	 * */
	public function get($abstract);


	/**
	 * 注册服务类实例
	 *
	 * @param  string  $abstract
	 * @param  object  $instance
	 * @return mixed
	 * */
	public function set($abstract, $instance);

	/**
	 * 存取数据
	 * 当$value的值是"null"时为取数据
	 *
	 * @param string $key
	 * @param string|array $value, 值是null为取数据
	 * @param string|array $default 取不到数据时指定默认值
	 * @return mixed
	 * */
	public function store($key, $value = null, $default = null);

	public function unstore($key);
}
