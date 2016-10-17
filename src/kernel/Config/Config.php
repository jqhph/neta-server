<?php
namespace NetaServer\Config;

use \Spyc;
use \NetaServer\Support\Arr;

class Config 
{
	protected $root = __ROOT__;//项目目录
	
	protected $conf = [];
	
	/**
	 * 服务注入规则数组
	 * */
	protected $regularInjection;
	
	/**
	 * 注册服务容器规则文件路径
	 * */
	protected $regularInjectionFile = 'data/config/container.php';

	protected $routeConfPath = 'data/config/route.yaml';# 路由规则配置文件

	protected $routerRules = [];
	
	protected $confPathPre = 'data/config/';
	
	protected $confPaths = [//配置文件路径
		'config' => 'config.yaml',
	];
	
	public function __construct() 
	{
		$this->load();
	}
	
	/**
	 * 获取配置信息
	 * 
	 * @param string $name 多级参数用"."隔开, 如 get('db.mysql')
	 * @param string|array|null $default 默认值
	 * */
	public function get($name = null, $default = null) 
	{
		return Arr::get($this->conf, $name, $default);
	}
	
	//获取所有配置信息
	public function all($filter = true) 
	{
		return $this->conf;
	}
	
	/**
	 * 获取注入规则
	 * */
	public function getInjectionConfig()
	{
		return include $this->root . $this->regularInjectionFile;
		//return $this->parseYAML($this->root . DIRECTORY_SEPARATOR . $this->regularInjectionFile);
	}

	/**
	 * 获取路由规则
	 * */
	public function getRouterRules()
	{
		if (! $this->routerRules) {
			$this->routerRules = $this->parseYAML($this->root . $this->routeConfPath);
		}
		return $this->routerRules;
	}

	/**
	 * 读取配置文件
	 * */
	public function load()
	{
		$this->conf = [];

		$pre = $this->root . $this->confPathPre;

		foreach ($this->confPaths as $file) {
			$filename = $pre .  $file;
			if (! is_file($filename)) {
				warn("找不到配置文件[$filename]！");
				exit;
			}
			$this->conf += $this->parseYAML($filename);
		}


		$adds = (array) $this->get('add-config');
		foreach ($adds as & $filename) {
			$file = "{$pre}{$filename}.php";
			if (! is_file($file)) {
				warn("找不到配置文件[$filename]！");
				exit;
			}
			$this->conf += include $file;
		}
		unset($config);
	}
	
	public function parseYAML($filename)
	{
		return Spyc::YAMLLoad($filename);
	}
	
}
