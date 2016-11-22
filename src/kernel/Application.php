<?php
namespace NetaServer;

use \NetaServer\Server\Swoole\Websocket;
use \NetaServer\Server\Swoole\Tcp;

class Application 
{
    /**
     * 服务容器
     * 
     * @var \NetaServer\Injection\Container
     * */
    protected $container;
    
    public function __construct($root)
    {
        if (! $root) {
            return warn('无法获取根目录路径！');
        }
        define('__ROOT__', $root . '/');
        
        define('JQH_START', microtime(true));
        
        info("\e[36m=================================启动服务...=================================\e[39m");
        
        $this->checkDependencies();
        
        $this->container = app();
        
        $this->container->instance('application', $this);
        
        $this->regist();
        
        $this->init();
        
        $this->run();
    }
    
    public function regist()
    {
        # 异常捕获, 此函数对swoole事件回调方法无效
        set_exception_handler([$this->container->make('exception.handler'), 'run']);
        # 注册错误处理事件
        set_error_handler([$this, 'errorHandle']);
        # 注册进程退出监控事件
        register_shutdown_function([$this, 'checkError']);
    }
    
    protected function init() 
    {
        if (C('php.debug', false)) {
            # 开发环境开启错误提示
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 1);
        } else {
            ini_set('error_reporting', -1);
            ini_set('display_errors', 'off');
            ini_set('log_errors', 'off');
        }
        
        # 项目目录
        define('__MODULE__', C('application', 'JQH'));
        
        # php相关配置
        date_default_timezone_set(C('php.timezone', 'PRC'));
        
        if (C('server.unixsock_buffer_size') > 1000) {
            # 修改进程间通信的UnixSocket缓存区尺寸
            ini_set('swoole.unixsock_buffer_size', C('server.unixsock_buffer_size'));
        }
    }
    
    /**
     * 依赖类库和扩展检查
     * */
    protected function checkDependencies()
    {
        if (! defined('SWOOLE_VERSION')) {
            error('请先安装swoole扩展, 详见 http://www.swoole.com/');
            exit;
        }
        
        if (version_compare(SWOOLE_VERSION, '1.8.0', '<')) {
            error('swoole扩展必须>=1.8版本');
            exit;
        }
        
        if (! class_exists('\\Swoole\\Server')) {
            error('你没有开启 swoole 的命名空间模式, 请在 php.ini 文件增加 swoole.use_namespace = true 参数. [执行 php --ini 命令可以查看 php.ini 位置]');
            exit;
        }
        
        if (! function_exists('msgpack_pack')) {
            error('请先安装msgpack扩展, 下载地址: https://pecl.php.net/package/msgpack');
            exit;
        }
        
        if (! class_exists('\Redis')) {
            error('请先安装redis扩展, 下载地址: https://pecl.php.net/package/redis');
            exit;
        }
        
        if (! class_exists('\Spyc')) {
            error('请先安装spyc类库');
            exit;
        }
    }
    
    protected function run()
    {
        # 选择启动的服务
        switch (C('server.type', 'WS')) {
            case 'WS':
                info('Websocket服务');
                new Websocket($this->container, 'Websocket');
                break;
            case 'TCP':
                info('TCP服务');
                new TCP($this->container, 'TCP');
                break;
            case 'UDP':
                new UDP($this->container, 'UDP');
                break;
            case 'Http':
                new Http($this->container, 'Http');
                break;
            default:
                warn('server类型错误！');
                exit;
        }
    
    }
    
    /**
     * 检查进程出错原因.
     *
     * @return void
     */
    public function checkError()
    {
        $log = '当前进程异常退出！';
        $error = error_get_last();
        if (empty($error)) {
            return false;
        }
        switch ($error['type']) {
            case E_ERROR :
        	case E_PARSE :
        	case E_CORE_ERROR :
        	case E_COMPILE_ERROR :
                $message = $error['message'];
                $file = $error['file'];
                $line = $error['line'];
                
                $pos = strpos($message, 'Stack trace:');
                if ($pos !== false) {
                    $message = substr($message, 0, $pos);
                }
                $log .= $message;//"$message [$file($line)]";
                
                if (isset($_SERVER['REQUEST_URI'])) {
                    $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                }
                
                $log = "$log [$file($line)]";
                
                if (! defined('STARTED')) {
                    error($log);
                }
                
                logger('exception')->error($log);
                break;
            default:
                break;
        }
    }
    
    /**
     * 全局错误处理
     * */
    public function errorHandle($code, $msg, $file, $line, $symbols)
    {
        $msg = "PHP.$msg [$file($line)]";
        
        logger('exception')->error($msg);
        
        if (! defined('STARTED')) {
            warn($msg);
        }
    }
	
}
