<?php
namespace NetaServer\ORM\DB;

class Redis
{
	private static $host;
	private static $port;
	private static $pwd;
	private static $db;
	private $redis;
	
	public function __construct(array $config = [])
	{
		ini_set('default_socket_timeout', -1);

		if (count($config) < 1) {
			$config = C('db.redis');
		}

		if (empty(self::$host)) {
			self::$host = $config['host'];
			self::$port = $config['port'];
			self::$pwd  = $config['pwd'];
			self::$db   = $config['db'];
		}
		
		$this->connect();
		$this->auth();//验证用户名
		$this->select();
		
	}
	
	private function dealErrorInfo($e)
	{
		app('exception.handler')->run($e);
	}
	
	////Redis server went away  Connection closed
	private function connect() 
	{
		try {
			$this->redis = new \Redis();
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
				return $this->redis->set($key, $value);
			else
				return $this->redis->setex($key, $timeout, $value);//key存在则替换原来的值 TTL方法获取剩余缓存时间
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
			return $this->redis->keys($where);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//自增一
	public function incr($key)
	{
		try {
			return $this->redis->incr($key);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	
	//获取字符串
	public function get($keys)
	{
		try {
			return $this->redis->get($keys);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}

	//默认一秒后过期
	public function expire($key, $second = 1)
	{
		try {
			$this->redis->expire($key, $second);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function del($key)
	{
		try {
			return $this->redis->del($key);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//****************************哈希操作
	
	//指定值+= $num即为要增加的整数，如果是减少则传负数即可
	public function hIncrby($key, $field, $num)
	{
		try {
			return $this->redis->hIncrBy($key, $field, $num);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
		
	}
	
	//批量插入哈希值
	public function hMset($key, $data)
	{
		try {
			return $this->redis->hMset($key, $data);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function hMget($key, $fields)
	{
		try {
			return $this->redis->hMget($key, $fields);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function hGetAll($key)
	{
		try {
			return $this->redis->hGetAll($key);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	
	//获取hash表中的单个field方法
	public function hGet($key, $field)
	{
		try {
			return $this->redis->hGet($key, $field);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	//给一个field存在则覆盖
	public function hSet($key, $field, $value)
	{
		try {
			return $this->redis->hSet($key, $field, $value);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	//******************************队列操作  lSet修改指定位置的值    lget返回指定位置的值  llen返回队列长度
	public function rpush($key, $val)
	{//往队列后边添加元素（右边），成功返回队列长度
		try {
			return $this->redis->rPush($key, $val);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lSet($key, $posi, $val)
	{
		try {
			return $this->redis->lSet($key, $val);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
		
	}
	
	public function lpop($key)
	{//移出并返回队列的头元素
		try {
			return $this->redis->lPop($key);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lRange($key, $start, $stop)
	{//返回指定位置的队列元素，如：key, 0, 4返回下标为0到4的队列值
		try {
			return $this->redis->lrange($key, $start, $stop);
		} catch (\RedisException $e) {
			$this->dealErrorInfo($e);
		}
	}
	
	public function lRemove($key, $val)
	{//删除指定值
		try {
			return $this->redis->lRemove($key, $val);
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
