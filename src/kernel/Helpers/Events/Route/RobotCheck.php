<?php
namespace JQH\Helpers\Events\Route;

/**
 * 机器人检测
 * @author   liu21st <liu21st@gmail.com>
 */
class RobotCheck
{
	protected $isRobot = null;	

	public function handle($router) 
	{
		// 机器人访问检测
		if(C('LIMIT_ROBOT_VISIT') !== false && $this->is()) {
			// 禁止机器人访问
			exit('Access Denied.');
		}
	}

	public function is() {
		if (is_null($this->isRobot)) 
		{
			if (! isset($_SERVER['HTTP_USER_AGENT'])) {
				return false;
			}
			$spiders = 'Bot|Crawl|Spider|slurp|sohu-search|lycos|robozilla';
			$browsers = 'MSIE|Netscape|Opera|Konqueror|Mozilla';
			if (preg_match("/($browsers)/", $_SERVER['HTTP_USER_AGENT'])) {
				$this->isRobot	= false ;
			} elseif (preg_match("/($spiders)/", $_SERVER['HTTP_USER_AGENT'])) {
				$this->isRobot = true;
			} else {
				$this->isRobot = false;
			}
		}
		return $this->isRobot;
	}
}
