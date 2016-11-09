<?php
namespace NetaServer\Router;

use \NetaServer\Injection\Container;
use \NetaServer\Support\Arr;

/**
 * 路由解析
 * */
class Dispatch 
{
	protected $container;
	
	//路由规则
	protected $rules;
	
	//表示“任意”的操作符
	protected $signOfAny = ':';
	
	/**
	 * 路由钩子
	 * */
	protected $hooks = [];
	
	//路由解析结果
	protected $matchResult;

	protected $requestMethod;

	protected $requestUri;
	
	protected $uriarr;
	
// 	protected $originalController;
// 	protected $originalAction;
	
	public $controllerName;//控制器名称
	public $actionName;//action名称
	public $auth;//接口验证相关配置信息
	public $requestParams;//其余参数
	
	const APP	   = 'APP';
	const NOTFOUND = 404;
	
	public function __construct(Container $container) 
	{
		$this->container = $container;
	}
	
	public function run(\Swoole\Http\Request $req, \Swoole\Http\Response $resp)
	{
		$this->requestMethod = Arr::getValue($req->server, 'request_method');
		$this->requestUri    = Arr::getValue($req->server, 'request_uri');

		$this->container->make('events')->fire('route.run.before', [$req, $resp]);
		
		//解析路由
		$this->setMatchResult($this->matchRouteRule());
		
		$this->afterMatch($req, $resp);
		
		return $this;
	}
	
	/**
	 * 匹配后置方法
	 * */
	protected function afterMatch($req, $resp)
	{
		if ($this->matchResult !== self::APP) {
			return;
		}
		
		$this->hooks['before'] = Arr::getValue($this->hooks, 'before', []);
		$this->hooks['after']  = Arr::getValue($this->hooks, 'after', []);

		$events = $this->container->make('events');
		
		//注册钩子
		foreach ((array) $this->hooks['before'] as & $hook) {
			$events->listen('route.run.after', $hook);
		}
		

		//注册验证通过后钩子事件
		foreach ((array) $this->hooks['after'] as & $hook) {
			$events->listen('route.auth.after', $hook);
		}
		
		$events->fire('route.run.after', [$req, $resp]);
	}
	
	//解析路由
	protected function matchRouteRule() 
	{
		$uri = $this->requestUri;
		
		$uriarr = explode('/', $uri);
		$this->arrayFilter($uriarr);
		
		$this->uriarr = & $uriarr;//保存
		
		$ulen = count($uriarr);
		
		//匹配路由
		foreach ($this->getRouterRules() as & $rule) {
			if ($this->match($rule, $uriarr, $ulen)) {
				return self::APP;
			}
		}
		
		return self::NOTFOUND;
	}
	
	protected function match(& $rule, & $uriarr, $ulen) 
	{
		if (empty($rule['route'])) {
			return false;
		}
		
		$method 	   = strtoupper(Arr::getValue($rule, 'method', 'GET'));
		$rule['route'] = explode('/', $rule['route']);
		
		$this->arrayFilter($rule['route']);
		
		$matching = false;
		if ($ulen == count($rule['route']) && (strpos($method, $this->getHttpMethod()) !== false || $method == '*')) {
		
			$matching = true;
			foreach ($rule['route'] as $k => & $r) {
				if (strpos($r, $this->signOfAny) === false && strtolower($r) != strtolower($uriarr[$k])) {
					$matching = false;
				}
			}
		}
			
		if (! $matching) {
			return false;	
		}

		//匹配成功

		if (! isset($rule['auth'])) {
			$rule['auth'] = '';
		}
		//接口验证
		$auth = (array) $rule['auth'];

		if (! isset($auth['enable'])) {
			$auth['enable'] = true;
		}
		$auth['class'] = isset($auth['class']) ? $auth['class'] : 'Auth';

		$contr = $action = '';
		
		$params = [];//参数
		
		$this->hooks = (array) Arr::getValue($rule, 'hooks', []);
		
		$ruleParams = Arr::get($rule, 'params', []);
		
		foreach ($ruleParams as $k => & $p) {
			switch ($k) {
				case 'controller':
					$contr = & $p;
					break;
				case 'action':
					$action = & $p;
					break;
				default:
					$params[$k] = & $p;
			}
		}

		$realContr = $realAction = '';
		foreach ($rule['route'] as $k => & $r) {
			if ($r == $contr) {
				$realContr = & $uriarr[$k];
			}
				
			if ($r == $action) {
				$realAction = & $uriarr[$k];
			}
				
			foreach ($params as $pn => & $param) {
				if ($param == $r) {
					$param = $uriarr[$k];
				}
			}
		}

		if (! $realContr) {
			$realContr = & $contr;
		}
		if (! $realAction) {
			$realAction = & $action;
		}
		
		if ($realContr) {//过滤特殊字符
			if (! preg_match('/^[A-Za-z](\/|\w)*$/', $realContr)) {
				return false;
			}
		}
		
		if ($realAction) {
			if (! preg_match('/^[A-Za-z](\/|\w)*$/', $realAction)) {
				return false;
			}
		}
		
		$this->controllerName = $realContr ?: 'Index';
		$this->actionName	  = $realAction ?: 'Index';
		$this->auth		      = & $auth;
		$this->requestParams  = & $params;
		
		return true;
	}
	
	public function getUri() 
	{
		return $this->uriarr;
	}

	//获取路由解析结果
	public function getMatchResult()
	{
		return $this->matchResult;
	}

	protected function setMatchResult($res)
	{
		$this->matchResult = $res;
	}
	
	//获取路由规则
	public function getRouterRules()
	{
		if (! $this->rules) {
			$this->rules = $this->getConfig()->getRouterRules();
		}
		return $this->rules;
	}
	
	//去除空值并重置key  array_values(array_filter($arr))
	protected function arrayFilter(& $arr) 
	{
		$new = [];
		foreach ($arr as & $row) {
			if (! $row) {
				continue;
			}
			$new[] = $row;
		}
		$arr = $new;
	}
	
	protected function getRequest() 
	{
		return $this->container->make('http.request');
	}
	
	protected function getConfig()
	{
		return $this->container->make('config');
	}

	protected function getHttpMethod() 
	{
		return $this->requestMethod;
	}
	
}
