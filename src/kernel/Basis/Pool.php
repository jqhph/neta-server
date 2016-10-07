<?php
/**
 * 对象池抽象类
 *
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/13
 * Time: 12:44
 */
namespace NetaServer\Basis;

use NetaServer\Exceptions\InternalServerError;
use \NetaServer\Injection\Container;

abstract class Pool
{
    /**
     * @var \NetaServer\Injection\Container
     * */
    protected $container;

    /**
     * 允许最大对象池存储的最大对象数量
     * */
    protected $max = 10;
    /**
     * 对象池
     * */
    protected static $pool = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 取对象
     * */
    public function pop($name = 'JQH')
    {
        if (! isset(self::$pool[$name])) {
            self::$pool[$name] = new \SplQueue();
        }
        if (! self::$pool[$name]->isEmpty()) {
            $instance = self::$pool[$name]->pop();

            # 清除超出容量的对象
            $count = $this->count($name);
            if ($count > $this->max) {
                for ($i = 1; $i <= $count - $this->max; $i++) {
                    self::$pool[$name]->pop();
                }
            }
            return $instance;
        }

        return $this->create($name);
    }

    /**
     * 归还对象
     * */
    public function push($name, $instance)
    {
        self::$pool[$name]->push($instance);
    }

    /**
     * 获取对象池对象数量
     *
     * @param string $name 名称
     * @return int
     * */
    public function count($name)
    {
        if (! isset(self::$pool[$name])) {
            return 0;
        }
        return self::$pool[$name]->count();
    }

    /**
     * 归还一个对象
     * */
    public function release($instance, $name = 'JQH')
    {
        if (! isset(self::$pool[$name])) {
            throw new InternalServerError('找不到[' . $name . ']！请确认对象池名称是否正确！');
        }
        if (method_exists($instance, 'reuse')) {
            $instance->reuse();
        }
        $this->push($name, $instance);
    }

    /**
     * 生产对象
     * */
    abstract protected function create($name);
}
