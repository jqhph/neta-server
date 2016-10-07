<?php
namespace Server\Swoole;

/**
 * User: jqh
 * Date: 16-9-12
 * Time: 17:11
 * */
class Tcp extends Server
{
	protected function create()
	{
		$this->server = new \Swoole\Server(C('server.host', '0.0.0.0'), C('server.port', 9588));
		$this->server->set(C('server.set'));
	}
	
	protected function bind()
	{
		$this->server->on('Connect', [$this, 'onManagerConnect']);
		$this->server->on('Receive', [$this, 'onManagerReceive']);
	}
	
	public function onManagerConnect()
	{
		
	}
	
	public function onManagerReceive()
	{
		
	}
}
