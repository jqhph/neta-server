<?php
namespace NetaServer\Utils\Log;

class Logger extends \Monolog\Logger
{
	protected $defaultLevelName = 'DEBUG';
	
	
	/**
	 * Get Level Code
	 * @param  string $level Ex. DEBUG, ...
	 * @return int
	 */
	public function getLevelCode($levelName)
	{
		$levelName = strtoupper($levelName);
	
		$levels = $this->getLevels();
	
		if (isset($levels[$levelName])) {
			return $levels[$levelName];
		}
	
		return $levels[$this->defaultLevelName];
	}
	
}
