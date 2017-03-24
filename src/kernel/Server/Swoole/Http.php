<?php
namespace NetaServer\Server\Swoole;

/**
 * Http服务器
 *
 * @date   2017-3-24 下午3:21:41
 * @author jqh
 */
class Http extends Server
{
    protected function create()
    {
        $this->server = new \Swoole\Http\Server(C('server.host', '0.0.0.0'), C('server.port', 9588));
        $this->server->set(C('server.set'));
    }
    
    protected function bind()
	{
        parent::bind();
        $this->server->on('Open',    [$this, 'onOpen']);
        $this->server->on('Request', [$this, 'onRequest']);
	}
	
    public function onRequest(\Swoole\Http\Request $req, \Swoole\Http\Response $resp)
    {
        try {
            $this->workerServer()->onRequest($req, $resp);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e){ 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
	
    public function onOpen(\Swoole\Server $serv, \Swoole\Http\Request $req)
    {
        try {
            $this->workerServer()->onOpen($serv, $req);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e){ 
            $this->container->make('exception.handler')->run($e);
            
        }
    }
	
}
