<?php
namespace lib\tools;

class Mongo {
	private static $host;
	private static $port;
	private static $user;
	private static $pwd;
	private static $db;
	private $mongo;//数据库连接
	private $mongoDB;//选择数据库
	private $mongoCollection;
	private $framework;
	
	
	public function __construct( $dbinfo = null, $framework = null) {
		if( $framework) {
			$this->framework = $framework;
		}else {
			$this->getFramework();
		}
		ini_set( 'default_socket_timeout', -1);
		if( empty( self::$host)) {
			$conf = $this->getConf( 'mongo');
			self::$host = ! empty( $dbinfo['host']) ? $dbinfo['host'] : $conf['host'];
			self::$port = ! empty( $dbinfo['port']) ? $dbinfo['port'] : $conf['port'];
			self::$user = ! empty( $dbinfo['user']) ? $dbinfo['user'] : $conf['user'];
			self::$pwd  = ! empty( $dbinfo['pwd'])  ? $dbinfo['pwd']  : $conf['pwd'];
			self::$db   = ! empty( $dbinfo['db'])   ? $dbinfo['db']   : $conf['db'];
		}
	
		$res = $this->connect();
		if( ! $res)
			return false;
		$this->selectDB();
	}
	
	
	//选择集合
	public function selectCollection( $table) {
		try{
			$this->mongoCollection = $this->mongoDB->selectCollection( $table);
			return $this->mongoCollection;
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	
	/*
	 * insert($array,array(‘w’=>false,’fsync’=>false,’timeout’=>10000))
	 * 完整格式:insert ( array $a [, array $options = array() ] )
	 *    insert(array(),array(‘w’=>false,’fsync’=>false,’timeout’=>10000))
	 *      w: 默认false,是否安全写入
	 *   	fsync: 默认false,是否强制插入到同步到磁盘
	 *      timeout: 超时时间(毫秒)
	 *
	 */
	public function insert( $data, $collection = null, $param = null) {
		try{
			if( ! $collection) {
				$collection = $this->mongoCollection;
			}
			if( $param === null) {
				$res = $collection->insert( $data);
				if( $res)
					return $data['_id'];//返回ID
				return false;
			}elseif( is_array( $param)) {
				$res = $collection->insert( $data, $param);
				if( $res) 
					return $data['_id'];
				return false;
			}
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	//修改集合
	/*
	$inc
	如果记录的该节点存在，让该节点的数值加N；如果该节点不存在，让该节点值等于N
	设结构记录结构为 array(’a’=>1,’b’=>’t’),想让a加5，那么：
	$coll->update(
			array(’b’=>’t’),
			array(’$inc’=>array(’a’=>5)),
	)
	$set
	让某节点等于给定值
	设结构记录结构为 array(’a’=>1,’b’=>’t’),b为加f，那么：
	$coll->update(
			array(’a’=>1),
			array(’$set’=>array(’b’=>’f’)),
	)
	$unset
	删除某节点
	设记录结构为 array(’a’=>1,’b’=>’t’)，想删除b节点，那么：
	$coll->update(
			array(’a’=>1),
			array(’$unset’=>’b’),
	)
	$push
	如果对应节点是个数组，就附加一个新的值上去；不存在，就创建这个数组，并附加一个值在这个数组上；如果该节点不是数组，返回错误。
	设记录结构为array(’a’=>array(0=>’haha’),’b’=& gt;1)，想附加新数据到节点a，那么：
	$coll->update(
			array(’b’=>1),
			array(’$push’=>array(’a’=>’wow’)),
	)
	这样，该记录就会成为：array(’a’=>array(0=>’haha’,1=>’wow’),’b’=>1)
	$pushAll
	与$push类似，只是会一次附加多个数值到某节点
	$addToSet
	如果该阶段的数组中没有某值，就添加之
	设记录结构为array(’a’=>array(0=& gt;’haha’),’b’=>1)，如果想附加新的数据到该节点a，那么：
	$coll->update(
			array(’b’=>1),
			array(’$addToSet’=>array(’a’=>’wow’)),
	)
	如果在a节点中已经有了wow,那么就不会再添加新的，如果没有，就会为该节点添加新的item——wow。
	$pop
	设该记录为array(’a’=>array(0=>’haha’,1=& gt;’wow’),’b’=>1)
	删除某数组节点的最后一个元素:
	$coll->update(
			array(’b’=>1),
			array(’$pop=>array(’a’=>1)),
	)
	删除某数组阶段的第一个元素
	$coll->update(
			array(’b’=>1),
			array(’$pop=>array(’a’=>-1)),
	)
	$pull
	如果该节点是个数组，那么删除其值为value的子项，如果不是数组，会返回一个错误。
	设该记录为 array(’a’=>array(0=>’haha’,1=>’wow’),’b’=>1)，想要删除a中value为 haha的子项：
	$coll->update(
			array(’b’=>1),
			array(’$pull=>array(’a’=>’haha’)),
	)
	结果为： array(’a’=>array(0=>’wow’),’b’=>1)
	$pullAll
	与$pull类似，只是可以删除一组符合条件的记录。
	*/
	public function update( $where, $updateData, $collection = null) {
		try{
			if( ! $collection) {
				$collection = $this->mongoCollection;
			}
			return $collection->update( $where, $updateData);
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	//查找单条数据
	public function findOne( $where = null, $collection = null, $fields = null) {
		try{
			if( ! $collection) {
				$collection = $this->mongoCollection;
			}
			if( ! $where) {
				if( ! is_array( $fields))
					return $collection->findOne();
				else 
					return $collection->findOne()->fields( $fields);
			}else {
				if( ! is_array( $fields))
					return $collection->findOne( $where);
				else 
					return $collection->findOne( $where)->fields( $fields);
			}
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	
	//$where=array(‘type’=>array(‘$exists’=>true),’age’=>array(‘$ne’=>0,’$lt’=>50,’$exists’=>true));
	public function find( $collection = null, $where = null, $fields = null, $limit = null, $skip = null, $sort = null) {
		try{
			if( ! $collection) 
				$collection = $this->mongoCollection;
			
			if( is_array( $where))
				$find = $collection->find( $where);
			else 
				$find = $collection->find();
			
			if( is_array( $fields))
				$find = $find->fields( $fields);
			
			if( $limit)
				$find = $find->limit( $limit);
			
			if( $skip)
				$find = $find->skip( $skip);
			
			if( is_array( $sort))
				$find = $find->sort( $sort);
			
			$return = [];
			if( $find) {
				foreach( $find as $id => $val) {
					$return[] = $val;
				}
			}
			return $return;
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	//索引
	public function ensureIndex( $key, $options = [], $collection = null) {
		try {
			if( ! $collection) {
				$collection = $this->mongoCollection;
			}

			
		} catch( \MongoException$e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	/**
	 * 获取记录条数
	 * @param $where array(‘age’=>array(‘$gt’=>50,’$lte’=>74))
	 * @return int
	 */
	public function getCount( $where = null, $collection = null) {
		try {
			if( ! $collection) {
				$collection = $this->mongoCollection;
			}
			if( is_array( $where))
				return $collection->count( $where);
			return $collection->count();
		} catch( \MongoException$e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	/**
	 * 强制关闭数据库的链接
	 */
	public function closeDb() {
		$this->mongo->close( TRUE);
	}
	
	//选择数据库
	public function selectDB() {
		try{
			$this->mongoDB = $this->mongo->selectDB( self::$db);
		}catch ( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	

	private function connect() {
		try{
			$connect = 'mongodb://' . self::$user . ':' . self::$pwd . '@' . self::$host . ':' . self::$port;
			if( class_exists( 'MongoClient')) {
				$this->mongo = new \MongoClient( $connect);
			}else {
				$this->mongo = new \Mongo( $connect);				
			}
			if( $this->mongo)
				return true;
			return false;
		} catch( \MongoException $e) {
			$this->dealErrorInfo( $e, 'mongo');
		}
	}
	
	public function getMongo() {
		return $this->mongo;
	}
	
	public function getMongoDB() {
		return $this->mongoDB;
	}
	
	private function getConf( $key = null) {
		return $this->framework->getConf( $key);
	}
	
	private function dealErrorInfo( $e, $log = 'mongo') {
		return $this->framework->dealErrorInfo( $e, $log);
	}
	
	protected function getFramework() {
		$this->framework = \lib\Framework::getInstance();
	}
}