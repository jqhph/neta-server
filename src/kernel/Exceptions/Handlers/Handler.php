<?php
namespace NetaServer\Exceptions\Handlers;

use \NetaServer\Injection\Container;

/**
 * 异常处理
 * */
class Handler
{
	/**
	 * \Config
	 * */
	protected $config;
	/**
	 * \NetaServer\Logger
	 * */
	protected $logger;
	
	protected $exceptionClasses = [
		'\PDOException' => 'db', 
		'\RedisException' => 'db',
		'\MongoException' => 'db',
		'\NetaServer\Exceptions\Exception' => 'app'
	];
	
	protected $logConfig;
	
	protected $defaultLogger = 'exception';//默认用exception日志处理方式
	
	public function __construct(Container $container) 
	{
		$this->config   = $container->make('config');
		$this->logger   = $container->make('logger');
		
		$this->logConfig = $this->config->get('log');
	}
	
	/**
	 * 数据库相关异常
	 * */
	public function db($e)
	{
		//获取日志处理配置信息
		
		$this->logger->db( 
				$e->getCode(), $e->getMessage(), 
				$e->getLine(), $e->getFile()
		);
		
	}
	
	/**
	 * 普通异常处理
	 * */
	public function normal($e)
	{
		//默认错误级别
		$level = \NetaServer\Exceptions\Exception::ERROR;
			
		$this->logger->exception(
				$level, $e->getMessage(),  $e->getCode(), $e->getLine(), $e->getFile()
		);
		
	}
	
	/**
	 * 系统自定义异常处理
	 * 
	 * */
	public function app($e, $level = false)
	{
		$level = $e->getLevel();

		//获取日志处理配置信息
		$logConfig = $this->getExcptionLogConfig();
		
		$this->logger->exception( 
				$level, $e->getMessage(),  $e->getCode(), $e->getLine(), $e->getFile(), $logConfig
			);
	}

	/**
	 * @var \Exception, \Error, \ParseError
	 * */
	public function run($e)
	{
		foreach ($this->exceptionClasses as $class => & $way) {
			$this->normalize($class);
			if ($e instanceof $class) {
				return $this->$way($e);
			}
		}
		return $this->normal($e);
	}
	
	/**
	 * 获取标准的类名
	 * */
	public function normalize(& $class)
	{
		is_string($class) ? ltrim($class, '\\') : $class;
	}
	
	//获取日志配置信息
	protected function getExcptionLogConfig() 
	{
		return $this->logConfig[$this->defaultLogger];
	}
	
}
