<?php
namespace NetaServer\ORM\DB;

use \NetaServer\Config\Config;
use \NetaServer\Support\Arr;
use \NetaServer\Exceptions\InternalServerError;

class PDO 
{
	/*
	 * 成员属性
	 */
	private $db_type;	//数据库类型
	private $host;		//主机名
	private $port;		//端口号
	private $user;		//用户名
	private $pass;		//密码
	private $charset;	//字符集
	private $dbname;	//数据库名称
	private $prefix;	//表前缀
	private $pdo;		//PDO实例化对象
	
	protected $config;
	# 是否启用连接池
	protected $usepool;
	
	# 是否开启debug模式
	protected $debug;

	/*
	 * 构造方法 初始化数据库连接
	 * @param array $arr   = array() 连接数据库信息数组
	 * @param bool  $error = true    true开启异常处理模式,false关闭异常处理模式
	 */
	public function __construct(array $dbConfig = null)
	{
		if ($dbConfig == null) {
			$dbConfig = C('db.mysql');
		}
		
		if (! $dbConfig) {
			throw new InternalServerError('DB CONFIG NOT FOUND');
		}

		$this->usepool = Arr::get($dbConfig, 'usepool');
		
		$this->db_type = Arr::get($dbConfig, 'type', 'mysql');
		$this->host    = Arr::get($dbConfig, 'host');
		$this->port    = Arr::get($dbConfig, 'port', 3306);
		$this->user    = Arr::get($dbConfig, 'user');
		$this->pass    = Arr::get($dbConfig, 'pwd');
		$this->charset = Arr::get($dbConfig, 'charset');
		$this->dbname  = Arr::get($dbConfig, 'name');
		$this->prefix  = Arr::get($dbConfig, 'prefix');

		//连接数据库
		$this->dbConnect();

		//设置为utf8编码
		$this->pdo->query('set names utf8');
	}
	
	/*
	 * 连接数据库
	 * 成功产生PDO对象,失败提示错误信息
	 */
	private function dbConnect()
	{
		try {
			$dsn = "{$this->db_type}:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

			if ($this->usepool) {
				$this->pdo = new \pdoProxy($dsn, $this->user, $this->pass);
			} else {
				$this->pdo = new \PDO($dsn, $this->user, $this->pass);
			}

			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);//开启异常处理
			return $this->pdo;
		} catch (\PDOException $e) {
			return $this->dealErrorInfo($e);
		}
	}

	public function release()
	{
		if ($this->usepool) {
			$this->pdo->release();
		}
	}
//--------------------------------------------------------------
// | 无预处理, 直接执行sql操作
//--------------------------------------------------------------	
	/**
	 * exec写操作
	 * */
 	public function exec($command)
	{
		try {
			$res = $this->pdo->exec($command);
			$this->release();
			return $res;
		} catch (\PDOException $e) {
			return $this->dealExecption($command, 'exec', $e);
		}
	}

	public function query($sql)
	{
		try {
			$res = $this->pdo->query($sql);;
			$this->release();
			return $res;
		} catch (\PDOException $e) {
			return $this->dealExecption($sql, 'query', $e);
		}
	}
	
	/**
	 * 查询多条数据
	 * */
	public function find($sql)
	{
		$stmt = $this->query($sql);
		return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : false;
	}
	
	
	/**
	 * 查询单条数据
	 * */
	public function findOne($sql) 
	{
		$stmt = $this->query($sql);
		return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
	}
	
	/*
	 * 新增数据操作
	* @param  string $sql 要处理的SQL语句
	* @return mixed       成功返回新插入数据自增长id,失败返回0
	*/
	public function dbInsert(& $sql)
	{
		$res = $this->exec($sql);
		$id  = $this->pdo->lastInsertId();
		if ($id) {
			return $id;
		}
		return $res;
	}
	
	# 批量添加
	public function batchAdd($table = '', array & $data)
	{
		$field  = '';
		$values = '';
		$key    = '';
		$vals   = '';
	
		foreach ($data as & $info) {
			if (empty($info))
				continue;
				
			foreach ($info as $k => & $v) {
				if ($key != 'ok')
					$key    .= '`' . $k . '`,';
				$vals .= '"' . $v . '",';
			}
			if (empty($field)) {
				$field  = substr($key,  0, - 1);
				$key    = 'ok';
			}
			$vals    = substr($vals,  0, - 1);
			$values .= '(' . $vals . '),';
			$vals    = '';
		}
		$values = substr($values, 0, -1);
	
		$sql = 'INSERT INTO `' . $table . '` (' . $field . ') VALUES ' . $values;
	
		return $this->dbInsert($sql);
	}
	
	
//--------------------------------------------------------------
// | 预处理执行sql操作
//--------------------------------------------------------------	
	
	/**
	 * 预处理
	 * */
	public function prepare($sql, array & $data, $select = true) 
	{
		try {
			$stmt = $this->pdo->prepare($sql);

			$this->release();
			if (! $stmt) {
				return false;
			}

			$stmt->execute($data);

			return $select ? $stmt : $stmt->rowCount();
		} catch (\PDOException $e) {
			$stmt = $this->dealExecption($sql, 'prepare', $e);

			if (! $stmt) {
				return false;
			}

			$stmt->execute($data);

			return $select ? $stmt : $stmt->rowCount();
		}
	}


	/**
	 * 查询单条数据操作
	 * 
	 * @param  string $sql 要处理的SQL语句
	 * @param array $whereData where字句值, 如: [48, '小强']
	 * @return mixed       成功返回关联一维数组,失败返回false
	 */
	public function dbGetRow($sql, array $whereData = [])
	{
		$stmt = $this->prepare($sql, $whereData);
		return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
	}

	/**
	 * 查询多条数据操作
	 * 
	 * @param  string $sql 要处理的SQL语句
	 * @param array $whereData where字句值, 如: [48, '小强']
	 * @return mixed       成功返回关联二维数组,失败返回false
	 */
	public function dbGetAll($sql, array $whereData)
	{
		$stmt = $this->prepare($sql, $whereData);
		
		return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : false;
	}
	
	
	
	/**
	 * 预处理修改
	 * 
	 * @param string $table
	 * @param array $data 要修改的数据
	 * @param string $where where字句, 参数值用"?"代替, 如: WHERE id = ? AND name = ?
	 * @param array $whereData where字句值, 如: [48, '小强']
	 * @return int 
	 * */
	public function update($table, array $data = [], $where = '', array $whereData = [])
	{
		$updateStr = '';
		foreach ($data as $key => $val) {
			if (strpos($key, '=') === false) {
				$updateStr .= '`' . $key . '` = ?,';
			} else {
				$updateStr .= $key . ' ?,';
			}
			
			$data[] = $val;
			unset($data[$key]);
		}
		
		foreach ($whereData as $v) {
			$data[] = $v;
		}
		
		$updateStr = substr($updateStr, 0, - 1);
		$sql = 'UPDATE `' . $table . '` SET ' . $updateStr . $where;
//debug($sql);die;
		return $this->prepare($sql, $data, false);
	}
	
	
	/*
	 * 添加数据
	*
	* @param string $table
	* @param array $data
	* @return boolean
	*/
	public function add($table, array $data) 
	{
		$field = '';
		$values = '';
		
		foreach ($data as $k => $v) {
			$field  .= '`' . $k . '`,';
			$values .= '?,';
			
			unset($data[$k]);
			$data[] = $v;
		}
		$field  = substr($field,  0, - 1);
		$values = substr($values, 0, - 1);

		$sql = 'REPLACE INTO `' . $table . '` (' . $field . ') VALUES (' . $values . ')';
		
		$res = $this->prepare($sql, $data, false);
		$id = $this->pdo->lastInsertId();
		if ($id) {
			return $id;
		}
		return $res;
		
	}
	
	public function delete($table, $where = '', array $whereData = []) 
	{
		$sql = 'DELETE FROM `' . $table . '` ' . $where;
		return $this->prepare($sql, $whereData, false);		
	}


	//异常处理
	private function dealExecption($sql, $fun, $e) {
		$res = $this->reConnect();//判断重连失效，是则重连，否则做其他处理
		if ($res) {
			return $this->pdo->$fun($sql);
		} else {
			//一次重连失败，再重连一次
			if ($this->dbConnect()) {
				$res = $this->pdo->$fun($sql);
				$this->release();
				return $res;
			}else {//再重连失败
				//错误处理
				$this->dealErrorInfo($e);
			}
		}
	}

	private function reConnect() {
		$errorInfo = $this->pdo->errorInfo();
		$errorNum = $errorInfo[1];
		$sqlStatus = $errorInfo[0];
		$errorMsg = $errorInfo[2];

		if ($errorNum == '2006' || $errorNum == '2013') {//mysql连接失效
			return $this->dbConnect();//重新连接
		}
	}

	private function dealErrorInfo($e)
	{
		app('exception.handler')->run($e);
	}

}
