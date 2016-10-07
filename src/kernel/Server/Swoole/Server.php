<?php
namespace NetaServer\Server\Swoole;

use \NetaServer\Exceptions\InternalServerError;
use \NetaServer\Injection\Container;
use \Swoole\Atomic;

/**
 * Created by PhpStorm.
 * User: jqh
 * Date: 16-9-12
 * Time: 17:17
 * */
abstract class Server
{
	/**
	 * 服务器类型
	 * Websocket, TCP, UDP, Http
	 * */
	protected $serverType;

	/**
	 * @var \NetaServer\Injection\Container
	 * */
	protected $container;

	/**
	 * @var \Swoole\Server
	 * */
	protected $server;
	
	/**
	 * @var Manager
	 */
	protected $manager;
	
	/**
	 * @var swoole_server_port
	 */
	protected $serverPort;
	
	/**
	 * 服务启动记录临时目录
	 * */
	protected $defaultTmplogPath = 'data/logs/start.log';

	/**
	 * 所有进程共用的服务。需要在server start前注入
	 * */
	protected $publicService = [
		'app.server', # $this
		'pipeline.manager',
		'mapper.manager',
		'exception.handler',
		'model.factory',
		'debug',
		'controller.manager',
		'server.timer',
		'application', # \NetaServer\Application
		'swoole.server', # \Swoole\Server
	];

	/**
	 * 用户自定义的server, 用于处理业务逻辑
	 * */
	protected $workerServer;
	
	public function __construct(Container $container, $type)
	{
		$this->container = $container;

		$this->serverType = $type;

		# 初始化定时器
		$container->get('server.timer')->init();

		$this->start();
	}
	
	# 监听其他服务
	protected function listen()
	{
		return;
		$host = C('listen.host', '0.0.0.0');
		$port = C('listen.port', 9959);
		$type = C('listen.type', SWOOLE_SOCK_TCP);

		info("listen ===> host: $host, port: $port, type: $type");
		
		$this->serverPort = $this->server->listen($host, $port, $type);
		# 设置分包协议
		$config = [
			'open_eof_check' => true,
			'open_eof_split' => false,
			'package_eof'    => C('listen.package_eof', "\r\n"),
		];
		$this->serverPort->set($config);
		
		$this->serverPort->on('Receive', function (\Swoole\Server $serv, $fd, $fromId, $data) {

		});

	}

	# 注册事件回调方法
	protected function bind()
	{
		# 注册自定义事件
		$events = app('events');
		$events->listen('server.run.before', [$this->workerServer(), 'beforeServerStart']);

		$this->server->on('WorkerStop',   [$this, 'onWorkerStop']);
		$this->server->on('Shutdown',     [$this, 'onShutdown']);
		$this->server->on('WorkerStart',  [$this, 'onWorkerStart']);
		$this->server->on('PipeMessage',  [$this, 'onPipeMessage']);
		$this->server->on('ManagerStart', [$this, 'onManagerStart']);
		$this->server->on('ManagerStop',  [$this, 'onManagerStop']);
		$this->server->on('Finish',       [$this, 'onFinish']);
		$this->server->on('Task',         [$this, 'onTask']);
		$this->server->on('Start',        [$this, 'onStart']);
		$this->server->on('WorkerError',  [$this, 'onWorkerError']);
		$this->server->on('Close',   	  [$this, 'onClose']);
	}

	/**
	 * 添加自定义的公共服务
	 *
	 * @param array|string $data
	 * @return void
	 * */
	public function addPublicService($data)
	{
		if (is_array($data)) {
			$this->publicService = array_merge($this->publicService, $data);
			return;
		}
		$this->publicService[] = $data;
	}
	
	public function onWorkerStart(\Swoole\Server $serv, $worker_id)
	{
		try {
			# 热加载[更新配置文件等信息] 清理所有服务
			$this->container->clear($this->publicService);

			define('WORKER_ID', $serv->worker_id);

			if (! defined('STARTED')) {
				define('STARTED', 1);
			}

			$workerNum = C('server.set.worker_num');

			# 调用用户自定义处理方法
			$this->workerServer()->onWorkerStart($serv, $worker_id);
// ----------------------------------------------------------------------------
// ---------------------------------------tasker进程---------------------------
// ----------------------------------------------------------------------------
			if ($worker_id >= $workerNum) {
				define('IS_WORKER', false);

				# 内存限制
				ini_set('memory_limit', c('server.task_worker_memory_limit') ?: '1G');
				if (($serv->worker_id - $workerNum) == 0) {
					info("Current server task worker memory limit is: ". ini_get('memory_limit'));
				}

				$processName = C('server.name') . ' server tasker num: ' . ($serv->worker_id - $workerNum) .
						 ' time:' . date('Y-m-d H:i:s') . ' pid ' . $serv->worker_pid;
				# 进程命名
				swoole_set_process_name($processName);

				app('file.manager')->appendContents(
						C('server.tmplog', $this->defaultTmplogPath),
						$processName . "\n"
				);

				return;
			}
// ------------------------------------------------------------------------
// ----------------------------------worker进程----------------------------
// ------------------------------------------------------------------------
			# worker进程
			define('IS_WORKER', true);

			# 内存限制
			ini_set('memory_limit', C('server.worker_memory_limit') ?: '1G');
			if ($worker_id == 0) {
				info("Current server worker memory limit is: ". ini_get('memory_limit'));
			}

			$processName = C('server.name') . ' server worker num: ' . $serv->worker_id . ' time:'
					. date('Y-m-d H:i:s') . ' pid ' . $serv->worker_pid;

			# 进程命名
			swoole_set_process_name($processName);
			# 临时文件
			app('file.manager')->appendContents(
					C('server.tmplog', $this->defaultTmplogPath),
					$processName . "\n"
			);

			if ($serv->worker_id == 0) {
				# 在第一个进程创建的时候开启定时器
				if ($timer = C('server.timer')) {
					foreach ($timer as $name => $v) {
						app('server.timer')->add($name, $v);
					}
				}

				info('worker进程数量: ' . $workerNum);
				if ($taskNum = C('server.set.task_worker_num')) {
					info('task进程数量: ' . $workerNum);
				}
			}
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onPipeMessage(\Swoole\Server $serv, $from_worker_id, $message)
	{
		try {
			$this->workerServer()->onPipeMessage($serv, $from_worker_id, $message);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onManagerStart(\Swoole\Server $serv)
	{
		try {
			$this->workerServer()->onManagerStart($serv);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onManagerStop(\Swoole\Server $serv, $worker_id)
	{
		try {
			logger('server')->error('manager进程异常退出！！worker_id: ' . $worker_id);
			$this->workerServer()->onManagerStop($serv, $worker_id);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onFinish(\Swoole\Server $serv, $data)
	{
		try {
			$this->workerServer()->onFinish($serv, $data);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onTask(\Swoole\Server $serv, $task_id, $from_id, $data)
	{
		try {
			$this->workerServer()->onTask($serv, $task_id, $from_id, $data);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onStart(\Swoole\Server $serv)
	{
		try {
			define('STARTED', 1);

			$processName = C('server.name') . ' server running ' . C('server.host') . ':' . C('server.port')
				. ' time:' . date('Y-m-d H:i:s') .
				' master:' . $serv->master_pid;

			swoole_set_process_name($processName);

			info($processName);

			app('file.manager')->appendContents(
				C('server.tmplog', $this->defaultTmplogPath),
				$processName . "\n"
			);

			$this->workerServer()->onStart($serv);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	# 进程异常退出。记录错误
	public function onWorkerError(\Swoole\Server $serv, $worker_id, $worker_pid, $exit_code)
	{
		try {
			$msg = 'worker进程异常退出！！worker_id: ' . $worker_id
					. '; worker_pid: ' . $worker_pid . '; exit_code: ' . $exit_code;

			logger('server')->error($msg);
			$this->workerServer()->onWorkerError($serv, $worker_id, $worker_pid, $exit_code);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onWorkerStop(\Swoole\Server $serv, $worker_id)
	{
		try {
			$msg = 'worker进程终止。worker_id: ' . $worker_id;
			logger('server')->info($msg);
			$this->workerServer()->onWorkerStop($serv, $worker_id);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onClose(\Swoole\Server $serv, $fd)
	{
		try {
			$this->workerServer()->onClose($serv, $fd);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onShutdown(\Swoole\Server $serv)
	{
		try {
			$msg = 'server终止';
			logger('server')->info($msg);
			$this->workerServer()->onShutdown($serv);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}

	# 启动服务
	protected function start()
	{
		$this->clear();

		$this->create();

		$this->inject();

		$this->listen();

		$this->bind();

		app('events')->fire('server.run.before', [$this]);

		$this->server->start();
	}

	abstract protected function create();

	/**
	 * 注入公用服务
	 * */
	protected function inject()
	{
		$this->container->set('app.server', $this);
		$this->container->set('swoole.server', $this->server);

		foreach ($this->publicService as $alias) {
			app($alias);
		}
	}
	# 获取服务器类型
	public function getServerType()
	{
		return $this->serverType;
	}

	# 获取用户自定义事件回调对象
	protected function workerServer()
	{
		if (! $this->workerServer) {
			$this->workerServer = $this->createWorkerServer();
			$this->container->set('worker.server', $this->workerServer);
		}
		return $this->workerServer;
	}

	protected function createWorkerServer()
	{
		$class = $this->serverType;
		$className = '\\App\\' . __MODULE__ . '\\Server\\' . $class;
		if (! class_exists($className)) {
			logger('server')->warning('找不到[' . $className . '], 请自定义业务逻辑处理类！');
			$className = '\\NetaServer\\Server\\Worker\\' . $class;
		}

		return new $className($this->container, $class);
	}

	protected function clear()
	{
		$file = __ROOT__ . C('server.tmplog', $this->defaultTmplogPath);
		if (is_file($file)) {
			unlink($file);
		}
	}

}
