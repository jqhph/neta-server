<?php
/**
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/13
 * Time: 16:33
 */
namespace NetaServer\Contracts\Pool;

interface PoolInterface
{
    /**
     * 获取一个对象
     * */
    public function pop();
    /**
     * 置入一个对象到池中
     * */
    public function push($instance);
    /**
     * 获取对象池中对象数量
     * */
    public function count();
    /**
     * 归还一个对象
     * */
    public function release($instance);
    /**
     * 创建一个对象
     * */
    public function create();
}
