<?php
namespace JQH\Injection;

use \JQH\Injection\Container;
use \JQH\Exceptions\InternalServerError;

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
				'class' => '\JQH\Injection\Container'
			],
			'config' => [
				'class' => '\JQH\Config\Config'
			],
			'events' => [
				'class' => '\JQH\Events\Dispatcher',
				'dependencies' => 'container'	
			],
			'repository' => [
				'class' => '\JQH\ORM\Repository',
				'dependencies' => ['mapperManager', 'container']	
			],
			'entityManager' => [
				'class' => '\JQH\ORM\EntityManager',
				'dependencies' => 'container'
			],
			'router' => [
				'class' => '\JQH\Router\Dispatch',
				'dependencies' => 'container'	
			],
			'controllerManager' => [
				'class' => '\JQH\Custom\ControllerManager',
				'dependencies' => 'container'	
			],
			'fileManager' => [
				'class' => '\JQH\Utils\File\FileManager',
				'dependencies' => 'config'
			],
			'http.client' => [
				'class' => '\\JQH\\Http\\Client'
			],
			'pipeline' => [
				'class' => '\JQH\Pipeline\PipelineManager',
				'dependencies' => 'container'	
			],
			'passwordHash' => [
				'class' => '\JQH\Utils\PasswordHash',
				'dependencies' => 'config'
			],
			'mapperManager' => [
				'class' => '\JQH\ORM\Mappers\MapperManager',
				'dependencies' => 'container'
			],
			'logger' => [
				'class' => '\JQH\Log\Logger',
				'dependencies' => 'container'
			],
			'modelFactory' => [
				'class' => '\JQH\Custom\ModelFactory',
				'dependencies' => 'container'
			],
			'cacheFactory' => [
				'class' => '\JQH\Cache\CacheFactory',
				'dependencies' => 'container'
			],
			'debug' => [
				'class' => '\JQH\Utils\Debug\Statistical',
				'dependencies' => 'container'
			],
			'exception.handler' => [
				'class' => '\JQH\Exceptions\ExceptionHandler',
				'dependencies' => 'container'
			],
			'events.robotCheck' => [
				'class' => '\JQH\Helpers\Events\Route\RobotCheck'
			],
			'pdo' => [
				'class' => '\JQH\ORM\DB\PDO'
			],
			'redis' => [
				'class' => '\JQH\ORM\DB\Redis'
			],

			'server.timer' => [
				'class' => '\JQH\Server\Worker\Timer',
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
			throw new InternalServerError('找不到[' . $abstract . ']服务。');
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
		return $this->container->get($alias);
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
			throw new InternalServerError('找不到[' . $abstract . ']服务。');
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
			$this->loadRule += (array) $this->container->get('config')->getInjectionConfig();
			$this->isLoadConfig = true;
		}
		return $this->loadRule;
	}

}
