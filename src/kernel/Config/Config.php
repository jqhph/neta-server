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
	
	protected $confPathPre = 'data/config/';
	
	protected $confPaths = [//配置文件路径
		'config'    => 'config.yaml',
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
	 * 读取配置文件
	 * */
	public function load()
	{
		$this->conf = [];

		foreach ($this->confPaths as $file) {
			$filename = $this->root . $this->confPathPre .  $file;
			if (! is_file($filename)) {
				warn("找不到[$filename]文件！");
				exit;
			}
			$this->conf += $this->parseYAML($filename);
		}
		
		unset($config);
	}
	
	public function parseYAML($filename)
	{
		return Spyc::YAMLLoad($filename);
	}
	
}
