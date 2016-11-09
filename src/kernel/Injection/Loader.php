<?php
namespace NetaServer\Injection;

use \NetaServer\Injection\Container;
use \NetaServer\Exceptions\InternalServerError;

//加载器
class Loader 
{
	protected $container;
	
	/**
	 * 是否载入配置文件
	 * */
	protected $isLoadConfig;
	
	/**
	 * 服务载入规则
	 * @key 服务别名
	 * @value ===> 
	 * 			   class string  类名
	 *	   		   dependencies array|string  依赖的服务 ===>
	 *									 	   服务别名1,  服务别名2 ...
	 * */
	protected $bindings = [//加载器加载信息（优先查找load*方法，找不到再从此数组信息中找）
            'container' => [
                'shared' => true,
                'class' => '\NetaServer\Injection\Container'
            ],
            'config' => [
        	    'shared' => true,
                'class' => '\NetaServer\Config\Config'
            ],
            'events' => [
        	    'shared' => true,
                'class' => '\NetaServer\Events\Dispatcher',
                'dependencies' => 'container'	
            ],
            'query' => [
        	    'shared' => true,
                'class' => '\NetaServer\ORM\Query',
                'dependencies' => ['builder.manager', 'container']	
            ],
            'router' => [
        	    'shared' => true,
                'class' => '\NetaServer\Router\Dispatch',
                'dependencies' => 'container'	
            ],
            'controller.manager' => [
        	    'shared' => true,
                'class' => '\NetaServer\Custom\ControllerManager',
                'dependencies' => 'container'	
            ],
            'file.manager' => [
        	    'shared' => true,
                'class' => '\NetaServer\Utils\File\FileManager',
                'dependencies' => 'config'
            ],
            'http.client' => [
        	    'shared' => true,
                'class' => '\\NetaServer\\Http\\Client'
            ],
            'pipeline.manager' => [
        	    'shared' => true,
                'class' => '\NetaServer\Pipeline\PipelineManager',
                'dependencies' => 'container'	
        	],
            'passwordHash' => [
        	    'shared' => true,
                'class' => '\NetaServer\Utils\PasswordHash',
                'dependencies' => 'config'
        	],
            'builder.manager' => [
        	    'shared' => true,
                'class' => '\NetaServer\ORM\Builders\BuilderManager',
                'dependencies' => 'container'
        	],
            'logger' => [
        	    'shared' => true,
                'class' => '\NetaServer\Log\Logger',
                'dependencies' => 'container'
            ],
            'model.factory' => [
        	    'shared' => true,
                'class' => '\NetaServer\Custom\ModelFactory',
                'dependencies' => 'container'
        	],
            'cache.factory' => [
        	    'shared' => true,
                'class' => '\NetaServer\Cache\CacheFactory',
                'dependencies' => 'container'
            ],
            'debug' => [
        	    'shared' => true,
                'class' => '\NetaServer\Utils\Debug\Statistical',
                'dependencies' => 'container'
            ],
            'exception.handler' => [
        	    'shared' => true,
                'class' => '\NetaServer\Exceptions\Handlers\Handler',
                'dependencies' => 'container'
            ],
            'events.robotCheck' => [
        	    'shared' => true,
                'class' => '\NetaServer\Helpers\Events\Route\RobotCheck'
            ],
            'pdo' => [
        	    'shared' => true,
                'class' => '\NetaServer\ORM\DB\PDO'
            ],
            'redis' => [
        	    'shared' => true,
                'class' => '\NetaServer\ORM\DB\Redis'
            ],
            'mongo' => [
        	    'shared' => true,
                'class' => '\NetaServer\ORM\DB\Mongo\Connection'
            ],
            'server.timer' => [
        	    'shared' => true,
        	    'class' => '\NetaServer\Server\Worker\Timer',
                'dependencies' => 'container'
            ],
        ];

	/**
	 * 载入服务实例并注册到服务容器上
	 * 
	 * @param $abstract string 载入类实例的别名
	 * @return instance
	 * */
    public function load($abstract, $binding) 
    {
        if (empty($binding['class'])) {
	       return false;
        }
        
        $className = $binding['class'];///////////////////////////////////////////////////////////////////////
        
        $dependencies = isset($binding['dependencies']) ? (array) $binding['dependencies'] : [];
        
        switch (count($dependencies)) {
            case 0:
    	       return new $className();
            	
            case 1: 
    	       return new $className($this->getDependencyInstance($dependencies[0]));
            
            case 2:
            	return new $className(
            		$this->getDependencyInstance($dependencies[0]),
            		$this->getDependencyInstance($dependencies[1])
            	);
            
            case 3:
            	return new $className(
            		$this->getDependencyInstance($dependencies[0]),
            		$this->getDependencyInstance($dependencies[1]),
            		$this->getDependencyInstance($dependencies[2])
            	);
            	
            default:
    	       return $this->getServiceInstance($className, $dependencies);
        		
        }
    }
	
	
    /**
     * 获取依赖类实例
     * 
     * @param string $alias 别名
     * @return instance
     * */
    protected function getDependencyInstance($alias)
    {
        return $this->make($alias);
    }
    
    /**
     * 根据别名获取类名
     * 
     * @param string $alias
     * @return string
     * */
    protected function getDependencyClass($alias) 
    {
        $loadRules = $this->getAllBindings();
        if (! isset($loadRules[$alias])) {
	       throw new InternalServerError('找不到依赖类信息');
        }
        	
        return $loadRules[$alias]['class'];
    }
    
    /**
     * 利用反射获取服务实例
     * @param $className string 要载入的类名
     * @param $params array $className类依赖的参数
     * */
    protected function getServiceInstance($className, array & $dependencies = []) 
    {
        $class = new \ReflectionClass($className);
        
        foreach ($dependencies as & $abstract) {
            $abstract = $this->getDependencyInstance($abstract);
        }
        
        return $class->newInstanceArgs($dependencies);
    }
	
	/**
	 * 获取注入详情
	 * */
    protected function getServiceBindings($abstract)
    {
        if (isset($this->bindings[$abstract])) {
        	return $this->bindings[$abstract];
        }
        
        $bindings = $this->getAllBindings();
        
        if (! isset($bindings[$abstract])) {
        	return false;
        }
        
        return $bindings[$abstract];
    }
	
    /**
     * 检查别名是否存在
     * 
     * @return bool
     * */
    public function loadAliasExists($alias)
    {
        $loadRules = $this->getAllBindings();
        return isset($loadRules[$alias]);
    }
    
    /**
     * 获取服务注册规则
     * */
    public function getAllBindings() 
    {
        if (! $this->isLoadConfig) {
        	$this->bindings += (array) $this->make('config')->getInjectionConfig();
        	$this->isLoadConfig = true;
        }
        return $this->bindings;
    }
	
}
		