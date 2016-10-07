<?php
namespace NetaServer\Server\Worker;

use \NetaServer\Exceptions\InternalServerError;
use \NetaServer\Injection\Container;

/**
 * worker进程基础类
 * 业务逻辑不推荐直接写在事件回调方法中
 * 可以在事件回调方法中进行自定义控制器分发逻辑, 在控制器中处理业务逻辑
 * 各事件回调方法请参考: http://wiki.swoole.com/wiki/page/41.html
 *
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/19
 * Time: 22:14
 */
abstract class Server
{
    protected $container;

    protected $controllerManager;

    protected $serverType;

    /**
     * @var \Server\Swoole\Server
     * */
    protected $server;

    public function __construct(Container $container, $serverType)
    {
        $this->container         = $container;
        $this->server            = $container->get('app.server');
        $this->serverType        = $serverType;
        $this->controllerManager = $container->get('controllerManager');
    }

    /**
     * 获取控制器
     *
     * @param string $name 控制器名称(类名), 大小写敏感
     * @return mixed
     * */
    protected function controller($name)
    {
        return $this->controllerManager->get($name);
    }

    /**
     * 服务启动前会调用此方法, 此方法可用于执行只能在服务启动前做的操作
     * 如初始化计数器、初始化内存共享table等
     * */
    public function beforeServerStart(\NetaServer\Server\Swoole\Server $server)
    {

    }

    public function onTask(\Swoole\Server $serv, $task_id, $from_id, $data)
    {

    }

    public function onPipeMessage(\Swoole\Server $serv, $from_worker_id, $message)
    {

    }

    public function onClose(\Swoole\Server $serv, $fd)
    {

    }

    public function onWorkerStart(\Swoole\Server $serv, $worker_id)
    {

    }

    public function onManagerStart(\Swoole\Server $serv)
    {

    }

    public function onManagerStop(\Swoole\Server $serv, $worker_id)
    {

    }

    public function onFinish(\Swoole\Server $serv, $data)
    {

    }

    public function onStart(\Swoole\Server $serv)
    {

    }

    public function onWorkerError(\Swoole\Server $serv, $worker_id, $worker_pid, $exit_code)
    {

    }

    public function onWorkerStop(\Swoole\Server $serv, $worker_id)
    {

    }

    public function onShutdown(\Swoole\Server $serv)
    {

    }

}
