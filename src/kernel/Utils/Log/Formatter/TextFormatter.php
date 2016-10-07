<?php
namespace NetaServer\Utils\Log\Formatter;

class TextFormatter extends \Monolog\Formatter\LineFormatter 
{
	const SIMPLE_FORMAT = "[%datetime%] %level_name%: %message% %context% %extra%\n";
	
}
