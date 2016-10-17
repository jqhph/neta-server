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
	protected $loadRule = [//加载器加载信息（优先查找load*方法，找不到再从此数组信息中找）
			'container' => [
				'class' => '\NetaServer\Injection\Container'
			],
			'config' => [
				'class' => '\NetaServer\Config\Config'
			],
			'events' => [
				'class' => '\NetaServer\Events\Dispatcher',
				'dependencies' => 'container'	
			],
			'query' => [
				'class' => '\NetaServer\ORM\Query',
				'dependencies' => ['mapper.manager', 'container']	
			],
			'router' => [
				'class' => '\NetaServer\Router\Dispatch',
				'dependencies' => 'container'	
			],
			'controller.manager' => [
				'class' => '\NetaServer\Custom\ControllerManager',
				'dependencies' => 'container'	
			],
			'file.manager' => [
				'class' => '\NetaServer\Utils\File\FileManager',
				'dependencies' => 'config'
			],
			'http.client' => [
				'class' => '\\NetaServer\\Http\\Client'
			],
			'pipeline.manager' => [
				'class' => '\NetaServer\Pipeline\PipelineManager',
				'dependencies' => 'container'	
			],
			'passwordHash' => [
				'class' => '\NetaServer\Utils\PasswordHash',
				'dependencies' => 'config'
			],
			'mapper.manager' => [
				'class' => '\NetaServer\ORM\Builders\MapperManager',
				'dependencies' => 'container'
			],
			'logger' => [
				'class' => '\NetaServer\Log\Logger',
				'dependencies' => 'container'
			],
			'model.factory' => [
				'class' => '\NetaServer\Custom\ModelFactory',
				'dependencies' => 'container'
			],
			'cache.factory' => [
				'class' => '\NetaServer\Cache\CacheFactory',
				'dependencies' => 'container'
			],
			'debug' => [
				'class' => '\NetaServer\Utils\Debug\Statistical',
				'dependencies' => 'container'
			],
			'exception.handler' => [
				'class' => '\NetaServer\Exceptions\Handlers\Handler',
				'dependencies' => 'container'
			],
			'events.robotCheck' => [
				'class' => '\NetaServer\Helpers\Events\Route\RobotCheck'
			],
			'pdo' => [
				'class' => '\NetaServer\ORM\DB\PDO'
			],
			'redis' => [
				'class' => '\NetaServer\ORM\DB\Redis'
			],
			'mongo' => [
				'class' => '\NetaServer\ORM\DB\Mongo'
			],
			'server.timer' => [
				'class' => '\NetaServer\Server\Worker\Timer',
				'dependencies' => 'container'
			],
//********************************************	
		];

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * 载入服务实例并注册到服务容器上
	 *
	 * @param $abstract string 载入类实例的别名
	 * @return instance
	 * */
	public function load($abstract)
	{
		$classInfo = $this->getServiceClass($abstract);

		if (! isset($classInfo['class'])) {
			return false;
// 			throw new InternalServerError('找不到[' . $abstract . ']服务。');
		}

		$className = $classInfo['class'];///////////////////////////////////////////////////////////////////////

		$dependencies = isset($classInfo['dependencies']) ? (array) $classInfo['dependencies'] : [];

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
				return $this->getInstance($className, $dependencies);

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
		return $this->container->make($alias);
	}

	/**
	 * 根据别名获取类名
	 *
	 * @param string $alias
	 * @return string
	 * */
	protected function getDependencyClass($alias)
	{
		$loadRules = $this->getLoadRules();
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
	protected function getInstance($className, array & $dependencies = [])
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
	protected function getServiceClass($abstract)
	{
		if (isset($this->loadRule[$abstract])) {
			return $this->loadRule[$abstract];
		}

		$loadRules = $this->getLoadRules();

		if (! isset($loadRules[$abstract])) {
			return false;
// 			throw new InternalServerError('找不到[' . $abstract . ']服务。');
		}

		return $loadRules[$abstract];
	}

	/**
	 * 检查别名是否存在
	 *
	 * @return bool
	 * */
	public function loadAliasExists($alias)
	{
		$loadRules = $this->getLoadRules();
		return isset($loadRules[$alias]);
	}

	/**
	 * 获取服务注册规则
	 * */
	public function getLoadRules()
	{
		if (! $this->isLoadConfig) {
			$this->loadRule += (array) $this->container->make('config')->getInjectionConfig();
			$this->isLoadConfig = true;
		}
		return $this->loadRule;
	}

}
