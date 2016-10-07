<?php
/**
 * ========================
 * 公共函数
 * 
 * Created by JQH.
 * Date: 16-9-9
 * Time: 09:42
 * ========================
 * */

use \NetaServer\Injection\Container;
use \NetaServer\Support\Str;

# 注册全局变量代替静态方法获取容器
$GLOBALS['__app__'] = Container::getInstance();

/**
 * 服务容器自动载入接口
 * 
 * @return instance
 * */
if (! function_exists('app')) {
	function app($abstract = null)
	{
		return $abstract ? $GLOBALS['__app__']->make($abstract) : $GLOBALS['__app__'];
	}
}

/**
 * 获取server类实例
 * 
 * @return \Server\Swoole\Server
 * */
if (! function_exists('serv')) {
	function serv()
	{
		return $GLOBALS['__app__']->get('app.server');
	}
}

/**
 * 获取配置信息
 * 
 * @param string $name 多级参数用"."隔开, 如 C('db.mysql')
 * @param string|array|null $default 默认值
 * */
if (! function_exists('C')) {
	function C($key, $default = null)
	{
		return $GLOBALS['__app__']->get('config')->get($key, $default);
	}
}

/**
 * 获取控制器
 *
 * @param string $name 控制器名称(类名), 大小写敏感
 * @return mixed
 * */
if (! function_exists('A')) {
	function A($name, $method = null, array $params = [])
	{
		return $GLOBALS['__app__']->get('controllerManager')->get($name);
	}
}


/**
 * 获取日志处理实例
 * 
 * @param sting 通道名称
 * @return instance
 * */
if (! function_exists('logger')) {
	function logger($channelName = 'exception')
	{
		return $GLOBALS['__app__']->get('logger')->getChannel($channelName);
	}
}

/**
 * Gets the value of an environment variable. Supports boolean, empty and null.
 *
 * @param  string  $key
 * @param  mixed   $default
 * @return mixed
 */
if (! function_exists('env')) {
	function env($key, $default = null)
	{
		$value = getenv($key);

		if ($value === false) {
			return $default;
		}

		switch (strtolower($value)) {
			case 'true':
			case '(true)':
				return true;

			case 'false':
			case '(false)':
				return false;

			case 'empty':
			case '(empty)':
				return '';

			case 'null':
			case '(null)':
				return;
		}

		if (strlen($value) > 1 && Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
			return substr($value, 1, -1);
		}

		return $value;
	}
}

/**
 * 获取http客户端方法
 * */
if (! function_exists('http')) {
	function http()
	{
		return $GLOBALS['__app__']->get('http.client');
	}
}

/**
 * 获取pdo, 每个进程持有不同的数据库连接
 * */
if (! function_exists('pdo')) {
	function pdo()
	{
		return $GLOBALS['__app__']->get('pdo');
	}
}
/**
 * 获取redis
 * */
if (! function_exists('redis')) {
	function redis()
	{
		return $GLOBALS['__app__']->get('redis');
	}
}

/**
 * 驼峰命名转化为下划线命名
 * */
if (! function_exists('get_db_field')) {
	function get_db_field($str)
	{
		$str = preg_replace_callback('/([A-Z])/', 'to_db_field', $str);
		return trim($str, '_');
	}
}

if (! function_exists('to_db_field')) {
	function to_db_field(& $text)
	{
		return '_' . strtolower($text[1]);
	}
}

if (! function_exists('info')) {
	function info($info)
	{
		$beg = "\x1b[33m";
		$end = "\x1b[39m";
		$str = $beg . date('[Y-m-d H:i:s]') . "{$end} - " . $info . "\n";

		echo $str;
	}
}

if (! function_exists('warn')) {
	function warn($info)
	{
		$beg = "\x1b[31m";
		$end = "\x1b[39m";
		$str = $beg . date('[Y-m-d H:i:s]') . "[warn]{$end} - " . $info . "\n";

		echo $str;
	}
}

/**
 * 调试函数
 * */
if (! function_exists('debug')) {
	function debug($data, $json = false)
	{
		if (is_string($data) || is_integer($data) || is_bool($data)) {
			echo date('[H:i:s]') . " $data\n";
		} elseif (is_array($data)) {
			if ($json) {
				return debug(json_encode($data));
			}
			print_r($data);
			echo "\n\n";
		} else {
			return debug(json_encode($data));
		}
	}
}
# 换行
if (! function_exists('nl')) {
	function nl($num = 1)
	{
		for ($i = 0; $i <= 1; $i++) {
			echo "\n";
		}
	}
}
