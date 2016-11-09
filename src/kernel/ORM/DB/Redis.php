<?php
namespace NetaServer\ORM\DB;

use \NetaServer\Support\Arr;

class Redis
{
	private static $host;
	private static $port;
	private static $pwd;
	private static $db;
	private $redis;

	protected $usepool;
	
	public function __construct(array $config = [])
	{
		ini_set('default_socket_timeout', -1);

		if (count($config) < 1) {
			$config = C('db.redis');
		}

		$this->usepool = Arr::getValue($config, 'usepool');

		if (empty(self::$host)) {
			self::$host = Arr::getValue($config, 'host');
			self::$port = Arr::getValue($config, 'port');
			self::$pwd  = Arr::getValue($config, 'pwd');
			self::$db   = Arr::getValue($config, 'db');
		}
		
		$this->connect();
		$this->auth();//验证用户名
		$this->select();
		
	}
	
	private function dealErrorInfo($e)
	{
		app('exception.handler')->run($e);
	}

	public function release()
	{
		if ($this->usepool) {
			$this->redis->release();
		}
	}
	
	////Redis server went away  Connection closed
	private function connect() 
	{
		try {
			if ($this->usepool) {
				$this->redis = new \redisProxy();
			} else {
				$this->redis = new \Redis();
			}

			$this->redis->connect(self::$host, self::$port, 0);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	private function auth()
	{
		try {
			$this->redis->auth(self::$pwd);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	public function select($index = null)
	{
		try {
			if ($index) {
				$this->redis->select($index);
			} else
				$this->redis->select(self::$db);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//设置字符串值
	//@param  timeout缓存时间（秒）
	public function set($key, $value = null, $timeout = null) {
		try {
			if ($timeout === null)
				$res = $this->redis->set($key, $value);
			else
				$res = $this->redis->setex($key, $timeout, $value);//key存在则替换原来的值 TTL方法获取剩余缓存时间
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//获取redis连接资源
	public function getResource() {
		return $this->redis;
	}
	
	
	//正则匹配key
	public function keys($where)
	{
		try {
			$res = $this->redis->keys($where);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//自增一
	public function incr($key)
	{
		try {
			$res = $this->redis->incr($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}


	//获取字符串
	public function get($keys)
	{
		try {
			$res = $this->redis->get($keys);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//默认一秒后过期
	public function expire($key, $second = 1)
	{
		try {
			$this->redis->expire($key, $second);
			$this->release();
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	public function del($key)
	{
		try {
			$res = $this->redis->del($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//************************************无序集合
	public function sAdd($key, $val)
	{
		try {
			$res = $this->redis->sAdd($key, $val);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//是否存在
	public function sIsMember($key, $val)
	{
		try {
			$res = $this->redis->sIsMember($key, $val);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	//返回集合成员个数
	public function sSize($key)
	{
		try {
			$res = $this->redis->sSize($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
    //删除成员
	public function sRem($key, $v)
	{
	   try {
			$res = $this->redis->sRem($key, $v);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//随机返回一个成员并删除
	public function sPop()
	{
		try {
			$res = $this->redis->sPop();
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//随机返回一个成员不删除
	public function sRandMember($key)
	{
		try {
			$res = $this->redis->sRandMember($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	public function __call($name, $arguments)
	{
		try {
			$res = call_user_func_array([$this->redis, $name], $arguments);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//****************************哈希操作
	
	//指定值+= $num即为要增加的整数，如果是减少则传负数即可
	public function hIncrby($key, $field, $num)
	{
		try {
			$res = $this->redis->hIncrBy($key, $field, $num);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
		
	}
	
	//批量插入哈希值
	public function hMset($key, $data)
	{
		try {
			$res = $this->redis->hMset($key, $data);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function hMget($key, $fields)
	{
		try {
			$res = $this->redis->hMget($key, $fields);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function hGetAll($key)
	{
		try {
			$res = $this->redis->hGetAll($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	
	//获取hash表中的单个field方法
	public function hGet($key, $field)
	{
		try {
			$res = $this->redis->hGet($key, $field);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	//给一个field存在则覆盖
	public function hSet($key, $field, $value)
	{
		try {
			$res = $this->redis->hSet($key, $field, $value);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//******************************队列操作  lSet修改指定位置的值    lget返回指定位置的值  llen返回队列长度
	public function rpush($key, $val)
	{//往队列后边添加元素（右边），成功返回队列长度
		try {
			$res = $this->redis->rPush($key, $val);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lSet($key, $posi, $val)
	{
		try {
			$res = $this->redis->lSet($key, $val);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
		
	}
	
	public function lpop($key)
	{//移出并返回队列的头元素
		try {
			$res = $this->redis->lPop($key);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lRange($key, $start, $stop)
	{//返回指定位置的队列元素，如：key, 0, 4返回下标为0到4的队列值
		try {
			$res = $this->redis->lrange($key, $start, $stop);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lRemove($key, $val)
	{//删除指定值
		try {
			$res = $this->redis->lRemove($key, $val);
			$this->release();
			return $res;
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function close()
	{
		$res = $this->redis->close();
		$this->redis = null;
		return $res;
	}
}
