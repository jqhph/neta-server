<?php
namespace JQH\Utils\Log;

use \JQH\Utils\Log\Logger;
use \Monolog\Handler\HandlerInterface;

class Base extends \JQH\Basis\Factory
{
	protected $channelName;
	
	protected $logConfig;
	
	protected function getLogConfig()
	{
		if (! $this->logConfig) {
			$this->logConfig = $this->getConfig()->get('log');
		}
		return $this->logConfig;
	}
	
	
	//create a log channel 		setFormatter(FormatterInterface $formatter)
	public function create($name)
	{
		$logger = new Logger($name);
		
		$config = $this->getLogConfig();
		
		if (! isset($config[$name])) {
			return $logger;
		}
	
        $this->pushHandlers($logger, (array) $config[$name]);
        
        return $logger;
	}
	
	/**
	 * 给日志通道添加自定义处理器
	 * */
	public function pushHandler(HandlerInterface $handle, $channelName = null)
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		$this->get($channelName)->pushHandler($handle);
		return $this;
	}
	
	/**
	 * 代理monolog处理日志方法
	 * 
	 * DEBUG (100): 详细的debug信息。
	 * INFO (200): 关键事件。
	 * NOTICE (250): 普通但是重要的事件。
	 * WARNING (300): 出现非错误的异常。
	 * ERROR (400): 运行时错误，但是不需要立刻处理。
	 * CRITICA (500): 严重错误。
	 * EMERGENCY (600): 系统不可用。
	 * */
	public function error($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->error($msg, $extra);
	}
	
	public function warning($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->warning($msg, $extra);
	}
	
	public function notice($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->warning($msg, $extra);
	}
	
	public function info($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->info($msg, $extra);
	}
	
	public function critica($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->critica($msg, $extra);
	}
	
	public function emergency($msg, $extra = [], $channelName = null) 
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->emergency($msg, $extra);
	}
	
	public function alert($msg, $extra = [], $channelName = null)
	{
		if (! $channelName) {
			$channelName = $this->channelName;
		}
		return $this->get($channelName)->alert($msg, $extra);
	}
	
	protected function getRequest() 
	{
		return $this->container->get('http.request');
	}
	
	protected function getConfig() 
	{
		return $this->container->get('config');
	}
}
