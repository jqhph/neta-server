<?php
namespace NetaServer\ORM\DB;

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
	private $logger;
	private $errorLog;
	//private $dealError = 'write';
	//private $common;

	/*
	 * 构造方法 初始化数据库连接
	 * @param array $arr   = array() 连接数据库信息数组
	 * @param bool  $error = true    true开启异常处理模式,false关闭异常处理模式
	 */
	public function __construct(array $config = []) {
		if (count($config) < 1) {
			$config = C('db.mysql');
		}

		$this->db_type = $config['type'];
		$this->host    = $config['host'];
		$this->port    = $config['port'];
		$this->user    = $config['user'];
		$this->pass    = $config['pwd'];
		$this->charset = $config['charset'];
		$this->dbname  = $config['name'];
		$this->prefix  = '';
		
		//连接数据库
		$this->dbConnect();

		//设置为utf8编码
		$this->dbR('set names utf8');
	}
	

	private function dealErrorInfo($e)
	{
		app('exception.handler')->run($e);
	}
	
	/*
	 * 连接数据库
	 * 成功产生PDO对象,失败提示错误信息
	 */
	private function dbConnect()
	{
		$dsn = "{$this->db_type}:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
		try {
			$this->pdo = new \PDO($dsn, $this->user, $this->pass);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);//开启异常处理
			return $this->pdo;
		} catch (\PDOException $e) {
			$this->dealErrorInfo($e);
		}
	}

//--------------------------------------------------------------
// | 无预处理, 直接执行sql操作
//--------------------------------------------------------------

	

	/*
	 * 写操作
	 * @param  string $sql 要处理的SQL语句
	 * @return mixed       成功返回受影响行数,失败提示错误信息
	 */
	  public function dbCUD($sql)
	  {
    	try {
    		return $this->pdo->exec($sql);
    	} catch (\PDOException $e) {
    		return $this->dealExecption($sql, 'exec', $e);
    	}
    }

	/*
	 * 读操作
	 * @param  string $sql 要处理的SQL语句
	 * @return mixed       成功返回PDOStatement对象,失败提示错误信息
	 */
	private function dbR($sql)
	{
		try {
			return $this->pdo->query($sql);
		} catch (\PDOException $e) {
			return $this->dealExecption($sql, 'query', $e);
		}
	}

	//异常处理
	private function dealExecption($sql, $fun, $e) {
		$res = $this->reConnect();//判断重连失效，是则重连，否则做其他处理
		if ($res) {
			return $this->pdo->$fun($sql);
		} else {
			//一次重连失败，再重连一次
			if ($this->dbConnect()) {
				return $this->pdo->$fun($sql);
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
	

	/*
	 * 新增数据操作
	 * @param  string $sql 要处理的SQL语句
	 */
	public function dbInsert($sql)
	{
		$res = $this->dbCUD($sql);
		$id = $this->pdo->lastInsertId();
		if ($id) {
			return $id;
		}
		return $res;
	}

	/*
	 * 查询单条数据操作
	 * @param  string $sql 要处理的SQL语句
	 * @return mixed       成功返回关联一维数组,失败返回false
	 */
	public function dbGetRow($sql)
	{
		$stmt = $this->dbR($sql);
		if (! $stmt) {
			return false;
		}
		return $stmt->fetch(\PDO::FETCH_ASSOC);
	}

	/* 
	 * 查询多条数据操作
	 * @param  string $sql 要处理的SQL语句
	 * @return mixed       成功返回关联二维数组,失败返回false
	 */
	public function dbGetAll($sql)
	{
		$stmt = $this->dbR($sql);
		if (! $stmt)
			return false;
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/*
	 * 更新数据
	*
	* @param string $table
	* @param array $data
	* @param string $where
	urn boolean
	*/
	public function update($table, array $data, $where = '')
	{
		//$table = $dbpre . $table;
		$updateStr = '';
		foreach ($data as $key => $val) {
			$updateStr .= '`' . $key . '`="' . $val . '",';
		}
		$updateStr = substr($updateStr, 0, - 1);
		$sql = 'UPDATE `' . $table . '` SET ' . $updateStr . $where;
		return $this->dbCUD($sql);
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

		foreach($data as $k => $v) {
			$field  .= '`' . $k . '`,';
			$values .= '"' . $v . '",';
		}
		$field  = substr($field,  0, - 1);
		$values = substr($values, 0, - 1);

		$sql = 'INSERT INTO `' . $table . '` (' . $field . ') VALUES (' . $values . ')';

		return $this->dbInsert($sql);
	}
	
	//批量添加
	public function bulkAdd($table, array $data)
	{
		$field  = '';
		$values = '';
		$key    = '';
		$vals   = '';

		foreach ($data as & $info) {
			if (empty($info))
				continue;
			
			foreach($info as $k => & $v) {
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
		//echo $sql . "\n\n";
		return $this->dbInsert($sql);
	}
	
	public function delete($table, $where = '')
	{
		$sql = 'DELETE FROM `' . $table . '` ' . $where;
		return $this->dbCUD($sql);
	}
}
