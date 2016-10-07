<?php
namespace JQH\Utils\Log\Handler;

use \Monolog\Logger;
use \JQH\Utils\Log\Formatter\DebugFormatter;

class DebugHandler extends \Monolog\Handler\StreamHandler 
{
	
	public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false) 
	{
		$this->formatter = new DebugFormatter();
		
		parent::__construct($stream, $level, $bubble);
	}
}
