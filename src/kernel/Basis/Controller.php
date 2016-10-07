<?php
namespace NetaServer\Basis;

use \NetaServer\Injection\Container;
use \NetaServer\Custom\ResponseCode;

class Controller 
{
	/**
	 * 中间件
	 * */
	protected $middleware = [];
	
	/**
	 * 对象容器
	 * */
	protected $container;
	
	protected $name;# 模块名称
	
	protected $tableName;
	
	protected $auth;
	
	/**
	 * 初始化方法
	 * */
	public function init()
	{
		
	}
	
	/**
	 * 获取模型对象
	 * 可以用M函数代替
	 * 
	 * @param $name string 模块名称（类名）
	 * @return Model Object
	 * */
	protected function model($name = null)
	{
		return $this->container->get('modelFactory')->get($name ?: $this->name);
	}
	
	/**
	 * 获取配置文件对象
	 * 使用方法：$this->getConfig()->get('db.mysql');
	 * 可以用config函数代替
	 *
	 * @return mixed
	 * */
	protected function getConfig() 
	{
		return $this->container->get('config');
	}
	
	/**
	 * 获取数据仓库（可以对数据进行增删改查），类似TP的D方法
	 * 在不需要模型的情况下建议直接调用此函数进行数据库操作 ，消耗较小
	 * 使用示例：
	 * 	R()->where('id', '>', 1)->read();
	 *
	 * @param string $name 模型名称，注意：大小写敏感！！！
	 * @return instance
	 * */
	protected function repository($name = null)
	{
		return $this->container->get('repository')->from($name ?: $this->tableName);
	}
	
	/**
	 * 设置中间件
	 * 
	 * $this->middleware('test');
	 * $this->middleware('test', ['only' => 'test1']);
	 * $this->middleware('test', ['only' => ['test1', 'test2']]);
	 * $this->middleware('test', ['except' => ['test1', 'test2']]);
	 * 
	 * only   => test1   只有 actionTest1方法会执行此中间件
	 * except => test1   除了actionTest1方法外的所有方法都会执行此中间件
	 * 
	 * @param string $middleware 中间件名称
	 * @param array  $options
	 * @return void
	 * */
	protected function middleware($middleware, array $options = [])
	{
		$this->middleware[$middleware] = &$options;
	}
	
	public function getMiddleware()
	{
		return $this->middleware;
	}
	
	/**
	 * 触发监听事件
	 * 
	 * @param string|object $event   事件名称或者事件名称的对象
	 * @param mixed			$payload 触发事件传入的参数
	 * @param bool			$halt	 是否在监听事件return后终止执行监听事件	 
	 * @return mixed		 
	 * */
	protected function event($event, $payload = [], $halt = false)
	{
		return $this->container->get('events')->fire($event, $payload, $halt);
	}
	
	/**
	 * 监听一个事件
	 * 
	 * @param string|array $events   事件名称，多个事件用数组
	 * @param callable	   $listener 事件
	 * @param int		   $priority 优先级权重
	 * @return void
	 * */
	protected function listen($events, $listener, $priority = 0)
	{
		return $this->container->get('events')->listen($events, $listener, $priority);
	}
	
	/**
	 * 获取日志处理对象
	 * */
	protected function logger() 
	{
		return $this->container->get('logger');
	}
	
	/**
	 * 调试日志
	 * 
	 * @param string|array $msg  要输出到日志的信息
	 * @param string 	   $path 日志路径, 仅第一次调用时有效, 默认data/logs/debug.log
	 * @return void
	 * */
	protected function debug($msg, $path = null) 
	{
		$this->logger()->debug($msg, $path);
	}
	
	/**
	 * 此对象封装好了get, post, put, delete等方法 
	 * */
	protected function getHttpClient() 
	{
		return $this->container->get('http.client');
	}
	
	public function setContainer(Container $container)
	{
		$this->container = $container;
	}
	
	public function setControllerName($controllerName)
	{
		$this->name      = & $controllerName;
		$this->tableName = get_db_field($controllerName);
	}
	
	/**
	 * 获取缓存类实例
	 * 可以用C函数代替
	 * */
	protected function cache() 
	{
		return $this->container->get('cacheFactory')->get();
	}
	
	protected function getContainer() 
	{
		return $this->container;
	}
	
}
