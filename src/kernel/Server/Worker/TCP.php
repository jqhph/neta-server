<?php 
namespace NetaServer\Server\Worker;

/**
 * TCP服务器事件回调类
 *
 * @date   2016-12-9 下午4:41:30
 * @author jqh
 */
class TCP extends Server
{
    public function onConnect(\Swoole\Server $serv, $fd)
    {
        
    }
    
    public function onReceive(\Swoole\Server $serv, $fd, $fromId, & $data)
    {
       
    }
}
