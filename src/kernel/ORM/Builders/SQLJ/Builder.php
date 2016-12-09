<?php
namespace NetaServer\ORM\Builders\SQLJ;

use \NetaServer\Exceptions\InternalServerError;
use \NetaServer\Contracts\Mapper\MapperInterface;

/**
 * 此sql构造器不会转译字段
 * 如:
 *   $this->select('myName'); ===> SELECT `myName` FROM ...
 * */
class Builder extends Base implements MapperInterface
{
    protected $container;
    
    protected $tableName;
    
    protected $field;
    
    protected $wheres = [];
    
    protected $whereData = [];
    
    protected $orWheres = [];
    
    protected $havingData = [];
    
    protected $having = [];
    
    protected $orHaving = [];
    
    protected $groupBy;
    
    protected $orderBy;
    
    protected $leftJoin = [];
    
    protected $limit;
	
    public function __construct(\NetaServer\Injection\Container $container) 
    {
        $this->container = $container;
    }
	
    public function from($table, $p2 = null) 
    {
        $this->tableName = $this->getConnection()->prefix . $table;
        return $this;
    }
	
    public function where($p1, $p2 = '=', $p3 = null, $table = null) 
    {
        $tb = $table ? $table : $this->tableName;
    
        $this->whereHandler($this->wheres, $tb, $p1, $p2, $p3, $this->whereData);
        return $this;
    }
	
	/**
	 * 多对多关联(不支持AS别名)
	 *
	 * @param $mid string 中间表表名
	 * @param $relate string 要关联的表
	 *
	 * 表结构 up      --- id
	 *     user    --- id
	 *     user_up --- user_id, up_id
	 ->from('up')
	 ->manyToMany('user_up', 'user')
	 相当于:
	  
	 ->from('up')
	 ->leftJoin('user_up', 'id', 'user_up.up_id')
	 ->leftJoin('user', '`user`.id', 'user_up.user_id')
	 	
	 SELECT * FROM `up`
	 LEFT JOIN `user_up` ON `user_up`.up_id = `up`.`id`
	 LEFT JOIN `user` ON `user_up`.user_id = `user`.id
	 * */
    public function manyToMany($mid, $relate)
    {
        $tmp = "`$mid`";
        return $this->leftJoin($mid, "$tmp.{$this->tableName}_id", 'id')
                    ->leftJoin($relate, "$tmp.{$relate}_id", "`$relate`.id");
        return $this; 
    }
	
	/**
	 * 多对多只关联中间表的情况
	 *
	 * @date   2016-11-9 下午1:14:31
	 * @author jqh
	 * @param  string $mid 中间表
	 * @param  string $as  别名
	 * @return $this
	 */
    public function relateMany($mid, $as = null)
    {
        if (! $as) {
            $as = "`$mid`";
        }
        return $this->leftJoin($mid, "$as.{$this->tableName}_id", 'id');
    }
	
	/**
	 * 必须先调用from方法！
	 *
	 * 表结构如下:
	 *  menu 		 --- menu_content_id
	 *  menu_content --- id, menu_type_id
	 *  menu_type	 --- id
	 *
	 * 使用示例:
	 *
	 Q('menu')
	 ->leftJoin('menu_content AS u', 'u.id', 'menu_content_id')
	 ->leftJoin('menu_type AS w', 'u.menu_type_id', 'w.id')
	 相当于
	 Q('menu')
	 ->belongTo('menu_content', 'u')
	 ->belongTo('menu_type', 'w', 'u')
	
	 SELECT * FROM `menu`
	 LEFT JOIN `menu_content` AS `u` ON u.id = `menu`.`menu_content_id`
	 LEFT JOIN `menu_type` AS `w` ON w.id = `u`.menu_type_id
	 *
	 * */
    public function belongsTo($table, $as = null, $table2 = null)
    {
        $left = $table;
        if ($as) {
            $as   = "`$as`";
            $left = "`$left` AS $as";
        } else {
            $as = "`$left`";
        }
        if (! $table2) {
            return $this->leftJoin($left, "$as.id", "{$table}_id");
        }
        
        return $this->leftJoin($left, "$as.id", "`$table2`.{$table}_id");
    }
	
	/**
	 * 获取统计数量
	 * */
	public function count($as = 'TOTAL')
	{
		return $this->select("COUNT(*) AS `$as`")->readRow();
	}
	
	public function sum($field, $as = 'SUM')
	{
		$this->select("SUM(`$field`) AS `$as`");
		return $this;
	}
	
	/**
	 * 跟上面belongsTo刚好相反, 必须先调用from方法！
	 * 表结构如下:
	 * 
	 * menu_content --- id
	 * menu			--- menu_content_id
	 
	 Q('menu_content')
 			->hasOne('menu')
 			->readRow();
 			
 SELECT *  FROM `menu_content` 
 	LEFT JOIN `menu` ON `menu`.menu_content_id = menu_content.`id` LIMIT 1			
	 * 
	 * 
	 * */
	public function hasOne($table, $as = null, $table2 = null)
	{
		$left = $table;
		if ($as) {
			$as   = "`$as`";
			$left = "`$left` AS $as";
		} else {
			$as = "`$left`";
		}
		if (! $table2) {
			return $this->leftJoin($left, 'id', "$as.{$this->tableName}_id");
		}
	
		return $this->leftJoin($left, "`$table2`.id", "$as.{$this->tableName}_id");
	}
	
	/**
	 * 解析where条件字句
	 * 
	 * @param array $data 保存where字句的数组
	 * @param string $TB where字句条件字段所属的表
	 * @param string|array $p1 where字句字段
	 * @param string|array|null $p2 操作符或条件值
	 * @param string|array $p3条件值
	 * @param array $prepareData 用于保存pdo预处理数据
	 * @param bool $autoAddTable 是否给字段加表名
	 * */
    protected function whereHandler(& $data, $TB, & $p1, $p2 = null, $p3 = null, & $prepareData, $autoAddTable = true)
    {
        $table = '';
        if ($autoAddTable) {
            $table = "`$TB`.";
        }
        //  ---------------------------------------------------------------
        //  | 第一个参数是数组
        // ----------------------------------------------------------------
        if (is_array($p1)) {
            foreach ($p1 as $field => & $val) {
                if (is_array($val)) {
                    if ($field == 'or' || $field == 'OR') {
                        $ors = [];
                        $this->whereHandler($ors, $TB, $val, null, null, $prepareData, $autoAddTable);
                        $data[] = '(' . implode(' OR ', $ors)  . ')';
                    } else {
                        $this->whereHandler($data, $TB, $field, $val[0], $val[1], $prepareData, $autoAddTable);
                    }
                	
                } else {
                    $this->normalizeWhereField($table, $field);
                    
                    switch (strtolower($val)) {
                        case 'is not null':
                            $data[] = "$field IS NOT NULL";
                            break;
                        case 'is null':
                            $data[] = "$field IS NULL";
                            break;
                        default:
                            // where字句
                            $data[] 	   = "$field = ?";
                            // 预处理绑定参数
                            $prepareData[] = $val;
                            break;
                    }
                }
            }
        	
        } elseif($p3 === null) {
            if ($p1 == 'or' || $p1 == 'OR') {
                $ors = [];
                $this->whereHandler($ors, $TB, $p2, null, null, $prepareData, $autoAddTable);
                $data[] = '(' . implode(' OR ', $ors)  . ')';
            		
            } else {
                $this->normalizeWhereField($table, $p1);
                switch (strtolower($p2)) {
                    case 'is not null':
                        $data[] = "$p1 IS NOT NULL";
                        break;
                    case 'is null':
                        $data[] = "$p1 IS NULL";
                        break;
                    default:
                        // where字句
                        $data[] 	   = "$p1 = ?";
                        // 预处理绑定参数
                        $prepareData[] = $p2;
                        break;
                }
            }
        	
        } else {
            $this->normalizeWhereField($table, $p1);
            
            switch (strtolower($p2)) {
                case '%like':
                case '%*':
                    $data[] 	   = "$p1 LIKE ?";
                    $prepareData[] = "%{$p3}";
                    break;
                case 'like%':	
                case '*%':
                    $data[] 	   = "$p1 LIKE ?";
                    $prepareData[] = "{$p3}%";
                    break;
                case '%like%':	
                case '%*%':
                    $data[] 	   = "$p1 LIKE ?";
                    $prepareData[] = "%{$p3}%";
                    break;
                case 'between':
                    $data[] = "$p1 BETWEEN ? AND ?";
                    $prepareData[] = $p3[0];
                    $prepareData[] = $p3[1];
                    break;
                case 'in':
                    foreach ($p3 as & $v) {
                        $prepareData[] = $v;
                        
                        $v = '?';
                    }
                    $data[] = $p1 . ' IN (' . implode(',', $p3) . ')';
                    break;
                default:
                    $data[] 	   = "$p1 $p2 ?";
                    $prepareData[] = $p3;
                    break;
            }
        }
    }
	
    protected function normalizeWhereField(& $table, & $field)
    {
        if (strpos($field, '.') === false) {
            $field = $table . '`' . $field . '`';
        }
    }
	
    public function orWhere($p1, $p2 = '=', $p3 = null, $table = null) 
    {
        $tb = $table ? $table : $this->tableName;
        
        $this->whereHandler($this->orWheres, $tb, $p1, $p2, $p3, $this->whereData);
        return $this;
    }
    
    public function having($p1, $p2 = '=', $p3 = null, $table = null)
    {
        $tb = $table ? $table : $this->tableName;
        
        $this->whereHandler($this->having, $tb, $p1, $p2, $p3, $this->havingData, false);
        return $this;
    }
	
    public function orHaving($p1, $p2 = '=', $p3 = null, $table = null)
    {
        $tb = $table ? $table : $this->tableName;
        
        $this->whereHandler($this->orHaving, $tb, $p1, $p2, $p3, $this->havingData, false);
        return $this;
    }
	
	
	/**
	 *  传入：
	 * [
	 'id', 'parentId', 'name',
	 'MenuContent' => ['content'], 'WechatMenuType' => ['code', 'menuType']
	 ]
	 * 返回:
	 `table`.`id` AS id,`table`.`parent_id` AS parentId,`table`.`name` AS name,
	 `menu_content`.`content` AS content,`wechat_menu_type`.`code` AS code,
	 `wechat_menu_type`.`menu_type` AS menuTyp
	 * */
    public function select($data) 
    {
        $this->fieldHandler($this->field, $data, $this->tableName);
        return $this;
    }
	
    protected function fieldHandler(& $fieldsContainer, & $data, $table) 
    {
        if (is_string($data)) {
            if (strpos($data, '(') !== false) {
                $fieldsContainer .=  $data . ', ';
            	
            } elseif (strpos($data, ' ') !== false || strpos($data, '.') !== false) {
                $fieldsContainer .= $data . ', ';
            	
            } elseif(strpos($data, '*') !== false) {
                $fieldsContainer .= $data . ', ';
            	
            } else {
                $fieldsContainer .= '`' . $table . '`.`' . $data . '`, ';
            	
            } 
        
        } else {
            foreach ($data as $k => & $v) {
                if (is_numeric($k)) {
                    $this->fieldHandler($fieldsContainer, $v, $table);
                	
                } else {
                    if (! is_array($v)) {
                        $fieldsContainer .= "`$table`.`$k` AS `$v`,";
                        continue;
                    }// $v是数组, $k是表名
                    $tb = $k;
                    foreach ($v as $i => & $f) {
                        if (is_numeric($i)) {
                        	$this->fieldHandler($fieldsContainer, $f, $tb);
                        	
                        } else {
                        	$fieldsContainer .= "`$tb`.`$i` AS `$f`,";
                        	
                        }
                    }
                }
            }
        }
    }
	
	/**
	 * 读取单行数据
	 * */
    public function readRow() 
    {
        if (! $this->tableName) {
            throw new InternalServerError('Can not found table name.');
        }
        
        $table  = "`$this->tableName`";
        
        $fields   = '';
        $leftJoin = '';
        $where    = '';
        $orderBy  = '';
        $groupBy  = '';
        $having	  = '';
        
        $this->getFieldsSql($fields);
        $this->getLeftJoinSql($leftJoin);
        $this->getWhereSql($where);
        $this->getOrderBySql($orderBy);
        $this->getGroupBySql($groupBy);
        
        if ($groupBy) {
            $this->getWhereSql($having, true);
            
            $this->whereData = $this->whereData + $this->havingData;
        }
        
        $limit = ' LIMIT 1';
        
        $sql = "SELECT $fields FROM {$table}{$leftJoin}{$where}{$groupBy}{$orderBy}{$having}{$limit}";
        
        $res = $this->getConnection()->dbGetRow($sql, $this->whereData);
        
        $this->clear();
        
        return $res;
    }
	
	/**
	 * 读取多行数据
	 * */
    public function read() 
    {
        if (! $this->tableName) {
            throw new InternalServerError('Can not found table name.');
        }
        
        $table  = "`$this->tableName`";
        
        $fields   = '';
        $leftJoin = '';
        $where    = '';
        $orderBy  = '';
        $groupBy  = '';
        $limit	  = '';
        $having   = '';
        
        $this->getFieldsSql($fields);
        $this->getLeftJoinSql($leftJoin);
        $this->getWhereSql($where);
        $this->getOrderBySql($orderBy);
        $this->getGroupBySql($groupBy);
        $this->getLimitSql($limit);
        
        if ($groupBy) {
            $this->getWhereSql($having, true);
            
            $this->whereData = $this->whereData + $this->havingData;
        }
        
        $sql = "SELECT $fields FROM {$table}{$leftJoin}{$where}{$groupBy}{$orderBy}{$having}{$limit}";
        
        $res = $this->getConnection()->dbGetAll($sql, $this->whereData);
        
        $this->clear();
        
        return $res;
    	
    }
	
    public function sort($order, $desc = true) 
    {
        $desc = $desc ? ' DESC' : ' ASC';
        
        $table = $this->tableName;
        
        $field = $order;
        # 数组, 表名=>字段名
        if (is_array($order)) {
            $table = key($order);
            
            $field = $order[$table];
            
            $this->orderBy = " ORDER BY $field $desc";
        } else {
            # 字符串	
            if (strpos($order, '.') === false) {
                $this->orderBy = " ORDER BY `$table`.`$field` $desc";
            } else {
                $this->orderBy = " ORDER BY $field $desc";
            }
        }
        
        return $this;
    }
	
	/**
	 * 用法:
	 *  $this->limit(0, 5); ===> LIMIT 0, 5
	 *
	 *  $this->limit(5);    ===> LIMIT 5
	 * */
    public function limit($p1, $p2 = 0) 
    {
        if (! $p2) {
	       $this->limit = " LIMIT $p1";		 
        } else
            $this->limit = " LIMIT $p1, $p2";
        return $this;
    }
	
	/**
	 * 传入数组或字符串
	 * */
    public function group($data) 
    {
        $this->groupHandler($this->groupBy, $this->tableName, $data);
        return $this;
    }
	
    protected function groupHandler(& $groupContainer, $table, & $data) 
    {
        if (is_array($data)) {
            foreach ($data as $k => & $field) {
                // $field = $this->changeToUnderlineOne($field);
                if (is_numeric($k)) {
                    $groupContainer .= "`$table`.`$field`,";
                } else {
                    $groupContainer .= "`$k`.`$field`,";
                }
            }
            $groupContainer = ' GROUP BY ' . rtrim($groupContainer, ',');
        } else {
            if (strpos($data, '.') === false && strpos($data, ',') === false) {
                $groupContainer = " GROUP BY `$table`.`$data`";
            } else {
                $groupContainer = " GROUP BY $data";
            }
        }
    }
		
	/**
	 * 传入：
	 * $this->leftJoin('menu_content AS u', 'u.id', 'menu_content_id')
	   ->leftJoin('wechat_menu_type AS w', 'u.wechat_menu_type_id', 'w.id')
	 *
	 * 返回：
	 LEFT JOIN `menu_content` AS u    ON `table`.`menu_content_id`      = `u`.`id`
	 LEFT JOIN `wechat_menu_type` AS w ON `u`.`wechat_menu_type_id` = `w`.`id`
	 * */
    public function leftJoin($table, $p1 = null, $p2 = null)
    {
        // 添加表前缀
        $table = $this->getConnection()->prefix . $table;
        
        if (strpos($table, ' ') === false) {
            $table = "`$table`";
        } 
        
        if (strpos($p1, '.') !== false && strpos($p2, '.') !== false) {
            $this->leftJoin[] = " LEFT JOIN $table ON $p1 = $p2";
        
        } elseif (strpos($p1, '.') !== false) {
            $this->leftJoin[] = " LEFT JOIN $table ON $p1 = `{$this->tableName}`.`$p2`";
        		
        } else {
            $this->leftJoin[] = " LEFT JOIN $table ON $p2 = `{$this->tableName}`.`$p1`";
        	
        }
        return $this;
    }
	
	public function union() 
	{
		
	}
	
    public function remove($id = null) 
    {
        if ($id) {
            $this->where('id', $id);
        }
        $where = '';
        $this->getWhereSql($where);
        $res = $this->getConnection()->delete($this->tableName, $where, $this->whereData);
        $this->clear();
        return $res;
    }
	
    public function delete($id = null) 
    {
        return $this->remove($id);
    }
	
    public function insert(array $p1) 
    {
        $res = $this->getConnection()->add($this->tableName, $p1);
        $this->clear();
        return $res;
    }
	
    public function add(array $p1)
    {
        $res = $this->getConnection()->add($this->tableName, $p1);
        $this->clear();
        return $res;
    }
	
	/**
	 * 用法:
	 * 	$this->update([
	 * 		'name' => '张三', 'age' => 18
	 * 	]);
	 *
	 *  $this->update('age', '+', 18);
	 *
	 *  $this->update('age', '+');
	 *
	 *  $this->update('age', '-');
	 * */
    public function update($p1, $p2 = null, $p3 = 1) 
    {
        if ($p2) {
            switch ($p2) {
                case '+':
                    $p1 = ["`$p1` = `$p1` +" => $p3];
                    break;
                case '-':
                    $p1 = ["`$p1` = `$p1` -" => $p3];
                    break;
                default:
                    $p1 = [$p1 => $p2];
            }
        		
        }
        
        $where = '';
        
        $this->getWhereSql($where);
        
        $res = $this->getConnection()->update($this->tableName, $p1, $where, $this->whereData);
        $this->clear();
        return $res;
    }
	
	// 字段值--
    public function incr($field, $step = 1)
    {
        return $this->update($field, '+', $step);
    }
	
	// 字段值++
    public function decr($field, $step = 1)
    {
        return $this->update($field, '-', $step);
    }
	
    public function insertBulk() 
    {
    	
    }
	
}
