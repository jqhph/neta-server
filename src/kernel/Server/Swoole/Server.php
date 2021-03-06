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
//         'container',
//         'NetaServer\Injection\Container',
//         'app.server', # $this
//         'application', # \NetaServer\Application
//         'swoole.server', # \Swoole\Server
//         'neta.server',# $this
    ];
    
    protected $ports;
    
    /**
     * 需要热更新的实体对象
     *
     * @var array
     */
    protected $staleInstances = [
        'config',
        'pdo',
        'redis',
        'mongo',
    ];
    
    /**
     * 用户自定义的server, 用于处理业务逻辑
     * */
    protected $workerServer;
    
    /**
     * 保存WorkerStart事件中传入的server
     *
     * @var 
     */
    protected $swooleWorkerServer;
    
    public function __construct(Container $container, $type)
    {
        $this->container = $container;
        
        $container->instance('neta.server', $this);
        
        $this->serverType = $type;
        
        # 初始化定时器
        $container->make('server.timer')->init();
        
        $this->start();
    }
    
    # 监听其他服务
    protected function listen()
    {
        // 监听配置
        $config = C('port-listen');
        if (count($config) < 1) {
            return;
        }
        
        foreach ((array) $config as $name => $c) {
            info("listen ===> {$c['host']}:{$c['port']}, type:{$c['type']}");
            $this->ports[$name] = $this->server->listen($c['host'], $c['port'], $c['type']);
            $this->ports[$name]->set($c['set']);
        
            $listener = null;
        
            // 设置回调事件
            foreach ($c['on'] as $event => $n) {
                list($class, $method) = parse_class_callable($n);
                if (! $listener) {
                    $listenerClass = $this->makeListenerClass($class);
                    $listener      = new $listenerClass($this->ports[$name], $c);
                }
                $this->ports[$name]->on($event, [$listener, $method]);
            }
        
            $listener = null;
        
            $registName = 'port.' . $name;
            // 注册port server到容器
            $this->container->instance($registName, $this->ports[$name]);
//             $this->publicService[] = $registName;
        }
    
    }
    
    // 获取监听者类名
    protected function makeListenerClass($name)
    {
        $class = 'App\\' . __MODULE__ . '\\Port\\' . $name;
        if (class_exists($class)) {
        	return $class;
        }
        return 'NetaServer\\Server\\Port\\' . $name;
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
//     public function addPublicService($data)
//     {
//         if (is_array($data)) {
//             $this->publicService = array_merge($this->publicService, $data);
//             return;
//         }
//         $this->publicService[] = $data;
//     }
    
    public function getSwooleWorkerServer()
    {
        return $this->swooleWorkerServer;
    }
    
    public function onWorkerStart(\Swoole\Server $serv, $workerId)
    {
        try {
            $this->container->instance('worker.server', $serv);
            
            // 注册错误处理handler
            $this->container->make('application')->regist();
            // 批量移除需要热更新的对象
            $this->container->dropInstances($this->staleInstances);
            
            # php相关配置
            date_default_timezone_set(C('php.timezone', 'PRC'));
            
            $this->swooleWorkerServer = $serv;
            
            define('WORKER_ID', $serv->worker_id);
            
            defined('STARTED') || define('STARTED', 1);
            
            $workerNum = C('server.set.worker_num', linux_cpu_num());
            
            # 调用用户自定义处理方法
            $this->workerServer()->onWorkerStart($serv, $workerId);
            
            // ----------------------------------------------------------------------------
            // ---------------------------------------tasker进程---------------------------
            // ----------------------------------------------------------------------------
            if ($workerId >= $workerNum) {
                return $this->taskerStart($serv, $workerId, $workerNum);
            }
            // ------------------------------------------------------------------------
            // ----------------------------------worker进程----------------------------
            // ------------------------------------------------------------------------
            $this->workerStart($serv, $workerId, $workerNum); 
                       
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    protected function workerStart($serv, $workerId, $workerNum)
    {
        define('IS_WORKER', true);
        
        # 内存限制
        ini_set('memory_limit', C('server.worker_memory_limit') ?: '1G');
        if ($workerId == 0) {
            info('Current server worker memory limit is: '. ini_get('memory_limit'));
            
            info('worker进程数量: ' . $workerNum);
            if ($taskNum = C('server.set.task_worker_num')) {
                info('task进程数量: ' . $workerNum);
            }
        }
        
        $processName = C('server.name') . ' worker num:' . $serv->worker_id . ' ['
            . date('Y-m-d H:i:s') . '] pid:' . $serv->worker_pid;
        
        # 进程命名
        swoole_set_process_name($processName);
        # 临时文件
        app('file.manager')->appendContents(
            __ROOT__ . C('server.tmplog', $this->defaultTmplogPath),
            $processName . "\n"
        );

        // 开启定时器
        app('server.timer')->handle($workerId);
        
    }
    
    protected function taskerStart($serv, $workerId, $workerNum)
    {
        define('IS_WORKER', false);
        
        # 内存限制
        ini_set('memory_limit', c('server.task_worker_memory_limit') ?: '1G');
        if (($serv->worker_id - $workerNum) == 0) {
            info('Current server task worker memory limit is: '. ini_get('memory_limit'));
        }
        
        $processName = C('server.name') . ' tasker num:' . ($serv->worker_id - $workerNum) .
        ' [' . date('Y-m-d H:i:s') . '] pid:' . $serv->worker_pid;
        # 进程命名
        swoole_set_process_name($processName);

        app('file.manager')->appendContents(
            __ROOT__ . C('server.tmplog', $this->defaultTmplogPath),
            $processName . "\n"
        );
    }
	
    public function onPipeMessage(\Swoole\Server $serv, $from_worker_id, $message)
    {
        try {
            $this->workerServer()->onPipeMessage($serv, $from_worker_id, $message);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onManagerStart(\Swoole\Server $serv)
    {
        try {
            $processName = C('server.name') . ' manager [' . date('Y-m-d H:i:s') . ']';
            # 进程命名
            swoole_set_process_name($processName);
            
            $this->workerServer()->onManagerStart($serv);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onManagerStop(\Swoole\Server $serv, $workerId = null)
    {
        try {
            logger('server')->error('manager进程异常退出');
            $this->workerServer()->onManagerStop($serv, $workerId);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onFinish(\Swoole\Server $serv, $data)
    {
        try {
            $this->workerServer()->onFinish($serv, $data);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onTask(\Swoole\Server $serv, $task_id, $from_id, $data)
    {
        try {
            $this->workerServer()->onTask($serv, $task_id, $from_id, $data);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onStart(\Swoole\Server $serv)
    {
        try {
            define('STARTED', 1);
            
            $processName = C('server.name') . ' master ' . C('server.host') . ':' . C('server.port')
            	. ' [' . date('Y-m-d H:i:s') .
            	'] pid:' . $serv->master_pid;
            
            swoole_set_process_name($processName);
            
            info($processName);
            
            app('file.manager')->appendContents(
                __ROOT__ . C('server.tmplog', $this->defaultTmplogPath),
                $processName . "\n"
            );
            
            $this->workerServer()->onStart($serv);
        } catch (\Exception $e) {
        	$this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    # 进程异常退出。记录错误
    public function onWorkerError(\Swoole\Server $serv, $workerId, $workerPid, $exitCode)
    {
        try {
            $msg = "worker进程异常退出！worker_id: $workerId; worker_pid: $workerPid; exit_code: $exitCode";
            
            logger('server')->error($msg);
            $this->workerServer()->onWorkerError($serv, $workerId, $workerPid, $exitCode);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onWorkerStop(\Swoole\Server $serv, $workerId)
    {
        try {
            $msg = 'worker进程终止。worker_id: ' . $workerId;
            logger('server')->info($msg);
            $this->workerServer()->onWorkerStop($serv, $workerId);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onClose($serv, $fd)
    {
        try {
            $this->workerServer()->onClose($serv, $fd);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
    
    public function onShutdown(\Swoole\Server $serv)
    {
        try {
            $msg = 'server终止';
            logger('server')->info($msg);
            $this->workerServer()->onShutdown($serv);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) { 
            $this->container->make('exception.handler')->run($e);
            
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
        $this->container->instance('app.server', $this);
        $this->container->instance('swoole.server', $this->server);
        
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
            $this->container->instance('worker.server', $this->workerServer);
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
