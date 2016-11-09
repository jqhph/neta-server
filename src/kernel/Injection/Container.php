<?php
namespace NetaServer\Injection;

use \NetaServer\Exceptions\InternalServerError;
use \NetaServer\Contracts\Container\ContainerInterface;

/**
 * 服务容器
 * */
class Container extends Loader implements ContainerInterface
{
    # 储存临时数据 
    protected $storage;
    
    # 对象池
    protected static $instances;
    
    private function __construct() 
    {
    	self::$instances[__CLASS__] = self::$instances['container'] = $this;
    }
    
    
    /**
     * 存取数据
     * 当$value的值是"null"时为取数据
     * 
     * @param string $key
     * @param string|array $value, 值是null为取数据
     * @param string|array $default 取不到数据时指定默认值
     * @return mixed
     * */
    public function store($key, $value = null, $default = null)
    {
        if ($value === null) {
    	   return isset($this->storage[$key]) ? $this->storage[$key] : $default;
        }
        
        $this->storage[$key] = $value;
        
        return $value;
    }
    
    public function unstore($key)
    {
        unset($this->storage[$key]);
    }
    
    
    /**
     * 获取一个服务
     * 
     * @param string $abstract 服务类名或别名
     * @return instance  closure
     * */
    public function make($abstract)
    {
        if (isset(self::$instances[$abstract])) {
        	return self::$instances[$abstract];
        }
        
        $binding = $this->getServiceBindings($abstract);
        
        if (! $instance = $this->load($abstract, $binding)) {
            if (! empty($binding['closure'])) {
                $instance = $binding['closure']($this);
            } else {
                return $this->build($abstract);
            }
        }
        
        if ($this->isShared($abstract)) {
            self::$instances[get_class($instance)] = self::$instances[$abstract] = $instance;
        }
        return $instance;
    }
    
    /**
     * 注入服务对象到容器
     * 
     * @param string $abstract 别名|类名
     * @param object $instance 对象
     * @return void
     * */
    public function instance($abstract, $instance)
    {
        if (! $abstract) {
            throw new InternalServerError('名称为空，注入容器失败');
        }
        if (! is_object($instance)) {
            throw new InternalServerError('instance不是一个类实例，注入容器失败');
        }
        self::$instances[$abstract] = $instance;
    }
    
    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param  string  $concrete
     * @param  array   $parameters
     * @return mixed
     */
    public function build($concrete)
    {
        // If the concrete type is actually a Closure, we will just execute it and
        // hand back the results of the functions, which allows functions to be
        // used as resolvers for more fine-tuned resolution of these objects.
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }
        
        $reflector = new \ReflectionClass($concrete);
        
        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface of Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (! $reflector->isInstantiable()) {
            if (! empty($this->buildStack)) {
                $previous = implode(', ', $this->buildStack);
                
                $message = "Target [$concrete] is not instantiable while building [$previous].";
            } else {
                $message = "Target [$concrete] is not instantiable.";
            }
            
            throw new InternalServerError($message);
        }
        
        $this->buildStack[] = $concrete;
        
        $constructor = $reflector->getConstructor();
        
        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if (is_null($constructor)) {
            array_pop($this->buildStack);
            
            return new $concrete;
        }
        
        $dependencies = $constructor->getParameters();
        
        $instances = $this->getDependencies($dependencies);
        
        array_pop($this->buildStack);
        
        return $reflector->newInstanceArgs($instances);
    }
    
    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getDependencies(array $parameters)
    {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            
            // If the class is null, it means the dependency is a string or some other
            // primitive type which we can not resolve since it is not a class and
            // we will just bomb out with an error since we have no-where to go.
            $dependencies[] = $this->resolveClass($parameter);
        }
        
        return $dependencies;
    }
    
    /**
     * Resolve a class based dependency from the container.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolveClass(\ReflectionParameter $parameter)
    {
        return $this->make($parameter->getClass()->name);
    }
    
    /**
     * Register a shared binding in the container.
     *
     * @param  string|array  $abstract
     * @param  \Closure|string|null  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * 注册一个服务
     * 
     * @param string|array $abstract 类名 或 类名=>别名
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared 是否单例
     * @return void
     * */
	public function bind($abstract, $concrete = null, $shared = false)
	{
	    // If the given types are actually an array, we will assume an alias is being
	    // defined and will grab this "real" abstract class name and register this
	    // alias with the container so that it can be used as a shortcut for it.
	    if (is_array($abstract)) {
	        list($abstract, $concrete) = $this->extractAlias($abstract);
	    }
	    
	    // If no concrete type was given, we will simply set the concrete type to the
	    // abstract type. This will allow concrete type to be registered as shared
	    // without being forced to state their classes in both of the parameter.
	    $this->dropStaleInstances($abstract);
	    
	    if (is_null($concrete)) {
	        $concrete = $abstract;
	    }
	    
	    if ($concrete instanceof \Closure) {
	        $concrete = [
	        	'closure' => $concrete,
	            'shared'  => $shared
	        ];
	    }
	    
	    if (is_string($concrete)) {
	    	$concrete = [
	    		'class'  => $concrete,
	    	    'shared' => $shared
	    	];
	    } else {
	    	$concrete['shared'] = $shared;
	    }
	    
	    $this->bindings[$abstract] = $concrete;
	}
	
	/**
	 * Determine if a given type is shared.
	 *
	 * @param  string  $abstract
	 * @return bool
	 */
	public function isShared($abstract)
	{
	    if (isset($this->instances[$abstract])) {
	        return true;
	    }
	
	    return empty($this->bindings[$abstract]['shared']) ? false : true;
	}
	
	/**
	 * Extract the type and alias from a given definition.
	 *
	 * @param  array  $definition
	 * @return array
	 */
	protected function extractAlias(array $definition)
	{
	    return [key($definition), current($definition)];
	}
	
    /**
     * Drop all of the stale instances and aliases.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        if (isset(self::$instances[$abstract])) {
            $class = get_class(self::$instances[$abstract]);
            unset(self::$instances[$class]);
        }
        unset(self::$instances[$abstract], $this->aliases[$abstract]);
    }
    
    /**
     * Get the Closure to be used when building a type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function ($c) use ($abstract, $concrete) {
            $method = ($abstract == $concrete) ? 'build' : 'make';
    
            return $c->$method($concrete);
        };
    }
    
    /**
     * 检测服务是否已注入容器
     * 
     * @return bool
     * */
    public function exist($abstract)
    {
        return isset(self::$instances[$abstract]);
    }
	
    /**
     * 获取所有已注入容器服务的名称
     * 
     * @return array
     * */
    public function getInstancesKeys() 
    {
        return array_keys(self::$instances);
    }
    
    public static function getInstance()
    {
        return isset(self::$instances['container']) ? self::$instances['container'] : new static();
    }
    
    public function clear($except = [])
    {
        $new = [];
        foreach ((array) $except as & $e) {
            if (isset(self::$instances[$e])) {
                $new[$e] = self::$instances[$e];
                
                $new[get_class(self::$instances[$e])] = self::$instances[$e];
            }
        }
        self::$instances = null;
        self::$instances = & $new;
    }
    
	/**
	 * 获取标准的类名
	 * */
// 	public function normalize($service)
// 	{
// 		return is_string($service) ? ltrim($service, '\\') : $service;
// 	}
	
	
    private function __clone() 
    {
    	
    }
}
