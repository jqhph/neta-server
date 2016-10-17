<?php
namespace NetaServer\ORM\DB;

class Mongo
{
    private $host;
    private $port;
    private $user;
    private $pwd;
    private $db;
    private $mongo;//数据库连接
    private $mongoDB;//选择数据库
    private $collection;


    public function __construct(array $dbinfo = null)
    {
        ini_set('default_socket_timeout', -1);

        $conf = $dbinfo ?: C('db.mongo');

        if (empty($this->host)) {
            $this->host = ! empty($dbinfo['host']) ? $dbinfo['host'] : $conf['host'];
            $this->port = ! empty($dbinfo['port']) ? $dbinfo['port'] : $conf['port'];
            $this->user = ! empty($dbinfo['user']) ? $dbinfo['user'] : $conf['user'];
            $this->pwd  = ! empty($dbinfo['pwd'])  ? $dbinfo['pwd']  : $conf['pwd'];
            $this->db   = ! empty($dbinfo['db'])   ? $dbinfo['db']   : $conf['db'];
        }

        $res = $this->connect();

        $this->selectDB();
    }


    //选择集合
    public function select($table)
    {
        try {
            return $this->collection = $this->mongoDB->selectCollection($table);
        } catch (\MongoException $e) {
            $this->dealErrorInfo($e);
        }
    }

    /**
     * 强制关闭数据库的链接
     */
    public function close()
    {
        $this->mongo->close(TRUE);
    }

    //选择数据库
    public function selectDB()
    {
        try {
            $this->mongoDB = $this->mongo->selectDB($this->db);
        } catch (\MongoException $e) {
            $this->dealErrorInfo($e);
        }
    }


    private function connect()
    {
        try {
            $connect = "mongodb://{$this->user}:{$this->pwd}@{$this->host}:{$this->port}";
            if (class_exists('MongoClient')) {
                $this->mongo = new \MongoClient($connect);
            } else {
                $this->mongo = new \Mongo($connect);
            }
            return $this->mongo;
        } catch(\MongoException $e) {
            $this->dealErrorInfo($e);
        }
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        try {
            return call_user_func_array([$this->collection, $name], $arguments);
        } catch(\MongoException $e) {
            $this->dealErrorInfo($e);
        }
    }

    public function getResource()
    {
        return $this->mongo;
    }

    public function getDb()
    {
        return $this->mongoDB;
    }

    protected function dealErrorInfo($e)
    {
        app('exception.handler')->run($e);
    }

}