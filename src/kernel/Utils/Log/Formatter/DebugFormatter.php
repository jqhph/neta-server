<?php
namespace NetaServer\Utils\Log\Formatter;

class DebugFormatter extends \Monolog\Formatter\LineFormatter 
{
	const SIMPLE_FORMAT = "[%datetime%] %message% %context% %extra%\n";
}
