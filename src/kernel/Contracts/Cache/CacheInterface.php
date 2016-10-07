<?php
namespace NetaServer\Contracts\Cache;

interface CacheInterface 
{
	public function get($key);
	
	public function set($key, $value, $timeout);
	
	public function remove($key);
	
	public function setTimeout($key, $timeout);
}
