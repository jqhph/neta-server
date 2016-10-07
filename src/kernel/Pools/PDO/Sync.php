<?php
/**
 * Created by PhpStorm.
 * User: jqh
 * Date: 2016/9/13
 * Time: 16:15
 */
namespace JQH\Pools\PDO;

class Sync extends \JQH\Basis\Pool
{
    protected $max = 30;

    public function create()
    {
        return $this->connect(C('db.mysql'));
    }

    protected function connect($conf)
    {
        $dsn = "{$conf['type']}:host={$conf['host']};port={$conf['port']};dbname={$conf['name']};charset={$conf['charset']}";
        try {
            $pdo = new \PDO($dsn, $conf['user'], $conf['pwd']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);//开启异常处理
            //设置为utf8编码
            $pdo->query('set names ' . C('db.mysql.charset', 'utf8'));
            return $pdo;
        } catch(\PDOException $e) {
            $this->container->get('exception.handler')->run($e);
        }
    }
}
