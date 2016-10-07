<?php
namespace NetaServer\Server\Worker;

/**
 * Websocket服务基础类
 *
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/19
 * Time: 14:53
 */
class Websocket extends Server
{

    public function onOpen(\Swoole\Server $serv, \Swoole\Http\Request $req)
    {

    }


    public function onRequest(\Swoole\Http\Request $req, \Swoole\Http\Response $resp)
    {

    }

    public function onMessage($serv, \Swoole\Websocket\Frame $frame)
    {

    }

}
