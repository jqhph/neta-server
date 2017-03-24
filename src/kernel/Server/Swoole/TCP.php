<?php
namespace NetaServer\Server\Swoole;

/**
 * User: jqh
 * Date: 16-9-12
 * Time: 17:11
 * */
class TCP extends Server
{
    /**
     * 是否需要手动拆包
     *
     * @var bool
     */
    protected $splitData;
    
    protected $openEofCheck;
    
    protected $eof;
    
    protected function create()
    {
        $this->server = new \Swoole\Server(C('server.host', '0.0.0.0'), C('server.port', 9588));
        $this->server->set(C('server.set'));
        
        $this->openEofCheck = C('server.set.open_eof_check');
        
        $this->eof = C('server.set.package_eof');
        
        $this->splitData = (C('server.set.open_eof_split') || C('server.set.open_length_check'));
    }
    
    protected function bind()
    {
        parent::bind();
    	$this->server->on('Connect', [$this, 'onConnect']);
    	$this->server->on('Receive', [$this, 'onReceive']);
    }
    
    public function onConnect(\Swoole\Server $serv, $fd)
    {
        try {
            $this->workerServer()->onConnect($serv, $fd);
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) {
            $this->container->make('exception.handler')->run($e);
             
        }
    }
    
    public function onReceive(\Swoole\Server $serv, $fd, $fromId, $data)
    {
        try {
            if ($this->splitData) {
                // 无需手动拆包
                return $this->workerServer()->onReceive($serv, $fd, $fromId, $data);
            }
            
            if (! $this->eof || ! $this->openEofCheck) {
                return $this->workerServer()->onReceive($serv, $fd, $fromId, $data);
            }
            
            // 需要手动拆包
            foreach (explode($this->eof, $data) as & $v) {
                $this->workerServer()->onReceive($serv, $fd, $fromId, $v);
            }
            
        } catch (\Exception $e) {
            $this->container->make('exception.handler')->run($e);
        } catch (\Error $e) {
            $this->container->make('exception.handler')->run($e);
        
        }
    }
	
}
