<?php
namespace JQH\Server\Worker;

use \JQH\Injection\Container;
use \Swoole\Atomic;
use \JQH\Support\Arr;
use \JQH\Exceptions\InternalServerError;

/**
 * 定时器管理类
 *
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/17
 * Time: 15:35
 */
class Timer
{
    protected $container;

    /**
     * @var \Server\Swoole\Server
     * */
    //protected $server;

    /**
     * 定时器id数组
     * */
    protected static $timeIds;

    /**
     * 定时器运行次数计数器
     *
     * @var \Swoole\Atomic
     * */
    protected static $timerRunCounter;

    /**
     * 计数器重置次数
     *
     * 每40亿重置1次, 所以总访问量应该是 `$this->counterX->get() * 4000000000 + $this->counter->get()`;
     *
     * @var \Swoole\Atomic
     */
    protected static $timerRunCounterX;

    protected static $listeners;

    /**
     * 每40亿重置一次计数器
     * */
    protected $X = 4000000000;

    public function __construct(Container $container)
    {
        $this->container = $container;
        //$this->server    = $container->get('app.server');
    }

    /**
     * 定时器回调函数
     * 所有定时器都会先回调此方法, 再回调用户配置的方法
     *
     * @param int    $timeId 定时器ID
     * @param string $name   定时器名称
     * */
    public function onTimer($timeId, $name)
    {
        if (! isset(self::$listeners[$name])) {
            return;
        }
        # 计数+1
        $this->addCount($name);
        # 回调指定的方法
        call_user_func(self::$listeners[$name], $timeId, $name);
    }

    /**
     * 增加计数
     * */
    protected function addCount($name)
    {
        if (! isset(self::$timerRunCounter[$name])) {
            return;
        }

        self::$timerRunCounter[$name]->add();

        # 计数器最多可以保存42亿数值, 所以超出40亿时重置
        if (self::$timerRunCounter[$name]->get() > $this->X) {
            self::$timerRunCounter[$name]->set(1);
            self::$timerRunCounterX[$name]->add();
        }
    }

    /**
     * 获取定时器运行次数(可以此方法判断定时器是否活跃)
     *
     * @return int
     */
    public function getCount($name)
    {
        return self::$timerRunCounterX[$name]->get() * $this->X + self::$timerRunCounter[$name]->get();
    }

    /**
     * 增加一个定时器
     *
     * @param string $name 定时器名称
     * @param array  $config 配置信息
     * @return void
     * */
    public function add($name, array $config)
    {
        if (! isset(self::$listeners[$name])) {
            warn('定时器[' . $name . ']开启失败！');
            return;
        }

        $interval = Arr::getValue($config, 'interval', 3000);

        # 开启定时器
        $this->tick($config['interval'], $name);
    }

    protected function addListener($name, callable $call)
    {
        self::$listeners[$name] = $call;
    }

    /**
     * 初始化操作
     * */
    public function init()
    {
        if (! $timer = C('server.timer')) {
            return;
        }
        foreach ($timer as $name => $v) {
            $call = $this->getListener($v['call']);
            if (! $call) {
                continue;
            }
            # 保存回调方法
            $this->addListener($name, $call);

            # 初始化计数器
            self::$timerRunCounter[$name]  = new Atomic();
            self::$timerRunCounterX[$name] = new Atomic();
        }

    }

    /**
     * 开启一个定时器
     *
     * @param int    $interval  时间间隔, 单位: 毫秒
     * @param string $timerName 定时器名称
     * @return void
     */
    protected function tick($interval, $timerName)
    {
        $aTime  = mt_rand(0, 99999);

        # 增加一个延迟执行的定时器
        swoole_timer_after($aTime, function () use ($interval, $timerName) {
            # 添加定时器
            self::$timeIds[$timerName] = swoole_timer_tick($interval, [$this, 'onTimer'], $timerName);

            logger('server')->info('定时器[' . $timerName . ']开启成功！定时器ID: ' . self::$timeIds[$timerName]);
        });
    }

    /**
     * 从控制器获取定时器回调方法
     *
     * @param string $listener
     * @return callable
     * */
    protected function getListener($listener)
    {
        list($class, $method) = $this->parseClassCallable($listener);

        $controller = $this->container->get('controllerManager')->get($class);

        if (! method_exists($controller, $method)) {
            $msg = "Cant't fount method: $method from $class!";
            warn($msg);
            throw new InternalServerError($msg);
        }

        return [$controller, $method];
    }

    /**
     * Parse the class listener into class and method.
     *
     * @param  string  $listener
     * @return array
     */
    protected function parseClassCallable(& $listener, $default = '')
    {
        $segments = explode('@', $listener);

        return [$segments[0], $this->getCallMethod(count($segments) == 2 ? $segments[1] : $default)];
    }

    protected function getCallMethod($method = '')
    {
        return 'onTimer' . $method;
    }
}
