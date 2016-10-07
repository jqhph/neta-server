<?php
namespace JQH\Utils\Middleware;

class Test
{
	public function handle($p1, $next)
	{
		echo "Test::handle: <br>";
		print_r(get_class($next));echo "<br><br>";
	}
	
	public function fuck($a, $v) 
	{
		echo "Test Fuck: $a $v <br>";
	}
}
