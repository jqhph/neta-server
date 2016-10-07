<?php
namespace JQH\Exceptions;

use \Monolog\Logger;

/**
 * 自定义异常父类
 * */
class Exception extends \Exception 
{
	/**
	 * 错误级别
	 * */
	protected $level;
	
	const WARNING 	= Logger::WARNING;//出现非错误的异常
	const ERROR 	= Logger::ERROR;//运行时错误，但是不需要立刻处理。
	const CRITICA   = Logger::CRITICAL;//严重错误。
	const EMERGENCY = Logger::EMERGENCY;//系统不可用。
	
	protected $code = 500;
	
	/**
	 * 异常类（抛出此异常时会中断程序运行并返回相关错误编码和错误信息）
	 * @param $message string 错误信息
	 * @param $level string 错误级别(当错误信息为空时不生效)，当设置为false或者NULL时不保存日志
	 * @param $code string or int 错误编码 
	 * */
	public function __construct($message = null, $level = self::ERROR, $code = null) 
	{
		if ($code !== null && $code !== false) {
			$this->code = $code;
		}
		
		if ($level !== null && $level !== false) {
			$this->checkLevel($level);
			$this->level = $level;
		}
		
		parent::__construct($message);
	}
	
	//是否保存日志
	public function ifSave() 
	{
		return $this->ifSave;
	}
	//获取错误级别
	public function getLevel() 
	{
		return $this->level;
	}
	
	private function checkLevel(& $level) {
		if ($level != self::ERROR && $level != self::WARNING && $level != self::CRITICA && $level != self::EMERGENCY) {
			$level = self::ERROR;
		}
	}
	/*
	 protected static $messages = [
	 		//Informational 1xx
	 		100 => '100 Continue',
	 		101 => '101 Switching Protocols',
	 		//Successful 2xx
	 		200 => '200 OK',
	 		201 => '201 Created',
	 		202 => '202 Accepted',
	 		203 => '203 Non-Authoritative Information',
	 		204 => '204 No Content',
	 		205 => '205 Reset Content',
	 		206 => '206 Partial Content',
	 		//Redirection 3xx
	 		300 => '300 Multiple Choices',
	 		301 => '301 Moved Permanently',
	 		302 => '302 Found',
	 		303 => '303 See Other',
	 		304 => '304 Not Modified',
	 		305 => '305 Use Proxy',
	 		306 => '306 (Unused)',
	 		307 => '307 Temporary Redirect',
	 		//Client Error 4xx
	 		400 => '400 Bad Request',
	 		401 => '401 Unauthorized',
	 		402 => '402 Payment Required',
	 		403 => '403 Forbidden',
	 		404 => '404 Not Found',
	 		405 => '405 Method Not Allowed',
	 		406 => '406 Not Acceptable',
	 		407 => '407 Proxy Authentication Required',
	 		408 => '408 Request Timeout',
	 		409 => '409 Conflict',
	 		410 => '410 Gone',
	 		411 => '411 Length Required',
	 		412 => '412 Precondition Failed',
	 		413 => '413 Request Entity Too Large',
	 		414 => '414 Request-URI Too Long',
	 		415 => '415 Unsupported Media Type',
	 		416 => '416 Requested Range Not Satisfiable',
	 		417 => '417 Expectation Failed',
	 		418 => '418 I\'m a teapot',
	 		422 => '422 Unprocessable Entity',
	 		423 => '423 Locked',
	 		//Server Error 5xx
	 		500 => '500 Internal Server Error',
	 		501 => '501 Not Implemented',
	 		502 => '502 Bad Gateway',
	 		503 => '503 Service Unavailable',
	 		504 => '504 Gateway Timeout',
	 		505 => '505 HTTP Version Not Supported'
	 		];
	* */
}
