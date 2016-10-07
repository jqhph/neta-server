<?php
namespace JQH\Utils\Middleware;

class Test2
{
	public function handle($router, $resq)
	{
		echo "Test2::handle: <br>";
		print_r(7777);echo "<br><br>";
	}
	
	public function fuck($a, $v) 
	{
		echo "Test Fuck: $a $v <br>";
	}
}
