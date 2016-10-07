<?php
namespace JQH\Server\Swoole;

use JQH\Exceptions\Exception;
use JQH\Exceptions\InternalServerError;
use \Swoole\Websocket\Server as WS;

/**
 * Created by PhpStorm.
 * User: jqh
 * Date: 16-9-12
 * Time: 17:01
 * */
class Websocket extends Server
{
	/**
	 * 缓存不完整的数据包 
	 * */
	protected $incompleteData;
	
	protected function create()
	{
		$host = C('server.host', '0.0.0.0');
		$port = C('server.port', 9588);

		info("host: $host, port: $port");

		$this->server = new WS($host, $port);
		$this->server->set(C('server.set'));
	}
	
	protected function bind()
	{
		parent::bind();
		$this->server->on('Open',    [$this, 'onOpen']);
		$this->server->on('Request', [$this, 'onRequest']);
		$this->server->on('Message', [$this, 'onMessage']);
	}
	
	public function onMessage(WS $serv, \Swoole\Websocket\Frame $frame)
	{
		try {
			# 拼接数据包
			$this->setup($frame);
			# finish != 1 表示数据包不完整
			if ($frame->finish != 1) {
				return false;
			}

			$this->workerServer()->onMessage($serv, $frame);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onRequest(\Swoole\Http\Request $req, \Swoole\Http\Response $resp)
	{
		try {
			$this->workerServer()->onRequest($req, $resp);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	public function onOpen(\Swoole\Server $serv, \Swoole\Http\Request $req)
	{
		try {
			$this->workerServer()->onOpen($serv, $req);
		} catch (\Exception $e) {
			$this->container->get('exception.handler')->run($e);
		}
	}
	
	/**
	 * 拼接完整数据包
	 * */
	protected function setup(\Swoole\Websocket\Frame $frame)
	{
		$fd = $frame->fd;
		if ($frame->finish != 1) {
			if (! isset($this->incompleteData[$fd])) {
				$this->incompleteData[$fd] = [$frame->data];
			} else {
				array_push($this->incompleteData[$fd], $frame->data);
			}
		} else {
			if (! isset($this->incompleteData[$fd])) {
				return;
			}
			if (count($this->incompleteData[$fd]) > 0) {
				$frame->data = implode('', $this->incompleteData[$fd]) . $frame->data;
				$this->incompleteData[$fd] = [];
			}
		}
		
	}
}
