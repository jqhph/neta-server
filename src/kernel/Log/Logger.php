<?php
namespace NetaServer\Log;

use \NetaServer\Support\Arr;
use \NetaServer\Utils\Log\Logger as Monolog;

/**
 * 日志处理 
 * */
class Logger extends \NetaServer\Utils\Log\Base 
{
	protected $levels = [
		100 => 'debug', 200 => 'info', 250 => 'notice', 550 => 'alert',
		400 => 'error', 300 => 'warning', 500 => 'critica', 600 => 'emergency'
	];
	
	protected $defaultExceptionConfig = [
			'channel' => 'exception',
			'path'    => 'data/logs/exception/record.log',
			'handlers' => [
				[
					'handler' 	=> 'DaysFileHandler',
					'formatter' => 'TextFormatter',
					'level' 	=> '100'
				]
			],
			'maxFiles' => 180,
			'filenameDateFormat' => 'Y-m-d'
		];
	
	
	
	/**
	 * 获取一个日志处理通道（对象）
	 * */
	public function getChannel($channelName) 
	{
		$this->get($channelName);//获取一个channel
		$this->channelName = $channelName;
		return $this;
	}
	
	
	public function db($code, $msg, $line, $file) 
	{
		//日志通道名称
		$channelName = 'db';
		
		$channel = $this->get($channelName);

		$addRecordMethod = 'error';
		
		$msg = "$msg [$file($line)]";
		
		$channel->error($msg);
		
		$this->displayError($msg);
	}
	
	protected function displayError($msg)
	{
		if (! defined('STARTED')) {
			error($msg);
		}
	}
	
	/**
	 * 异常日志处理方法
	 * 
	 * @param $config array 配置信息
	 * @param $level string 错误级别
	 * @param $msg string 错误信息
	 * @param $code int 错误编码
	 * @param $line int 错误行号
	 * @param $file string 错误文件
	 * @return null
	 * */
	public function exception($level, $msg, $code, $line = null, $file = null) 
	{
		//日志通道名称
		$channel = $this->get('exception');

		$addRecordMethod = 'error';
		if (isset($this->levels[$level])) {
			$addRecordMethod = $this->levels[$level];
		}
		
		$msg = "$msg [$file($line)]";

		$channel->$addRecordMethod($msg);
		
		$this->displayError($msg);
	}
	
	/**
	 * 给logger channel添加handler
	 * */
	protected function pushHandlers(Monolog $channel, array $config) 
	{
		$defaultConfig = & $this->defaultExceptionConfig;
		//日志路径
		$path				= Arr::get($config, 'path', $defaultConfig['path'], true);
		//日志handler处理器信息
		$handlers		    = Arr::get($config, 'handlers', $defaultConfig['handlers'], true);
		//目录下最大文件数
		$maxFiles			= Arr::get($config, 'maxFiles', 180);
		//日期格式化
		$filenameDateFormat = Arr::get($config, 'filenameDateFormat', 'Y-m-d');
		
		if (! $maxFiles) {
			$maxFiles = 0;
		}
		
		if (count($handlers) < 1) {
			$handlers = & $defaultConfig['handlers'];
		}
		
		foreach ($handlers as & $info) {//添加日志处理器
			if (! $info || count($info) < 1) {
				continue;
			}
		
			$handler = Arr::get($info, 'handler', $defaultConfig['handlers'][0]['handler']);
				
			$handelClass = '\\NetaServer\\Utils\\Log\\Handler\\' . $handler;
			if (! class_exists($handelClass)) {
				$handelClass = '\\Monolog\\Handler\\' . $handler;
			}
		
			$lowestLevel = Arr::get($info, 'level', \Monolog\Logger::DEBUG);
			$bubble      = Arr::get($info, 'bubble', true);
			$path		 = Arr::get($info, 'path', $path, true);

			$handler = new $handelClass($path, $lowestLevel, $bubble);//实例化日志处理器
				
			if (($maxFiles || $maxFiles === 0) && method_exists($handler, 'setMaxFiles')) {
				$handler->setMaxFiles($maxFiles);
			}
				
			if ($filenameDateFormat && method_exists($handler, 'setDateFormat')) {
				$handler->setDateFormat($filenameDateFormat);
			}
		
			if (! empty($info['formatter'])) {
				$fomatterClass = '\\NetaServer\\Utils\\Log\\Formatter\\' . $info['formatter'];
				if (! class_exists($fomatterClass)) {
					$fomatterClass = '\\Monolog\\Formatter\\' . $info['formatter'];
				}
					
				$handler->setFormatter(new $fomatterClass());
			}
		
			$channel->pushHandler($handler);//添加handler
		}
	}
	
	
	
	/**
	 * 输出调试信息到文本
	 * 
	 * @param $msg string|array|obj 调试信息，可以是字符串或数组，对象 
	 * @param $path string 输出调试信息路径，默认为 logs/debug.log
	 * return null 
	 * */
	public function debug($msg, $path = null) 
	{
		if(! isset($this->instances['debug'])) {
			$this->instances['debug'] = new \NetaServer\Utils\Log\Logger('debug');
			if (! $path) {
				$path = 'data/logs/debug.log';
			}
			
			$this->instances['debug']->pushHandler(new \NetaServer\Utils\Log\Handler\DebugHandler($path));
		}
		
		$content = & $msg;
		if (is_array($msg) || is_object($msg)) {
			$content = json_encode($msg);
		}
		
		$record = 'MSG: ' . $content;
		$this->instances['debug']->debug($record, []);
	}
	
}
