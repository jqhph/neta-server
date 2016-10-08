<?php
namespace Neta\ORM\Builders\SQLJ;

use \Neta\Exceptions\InternalServerError;
use \Neta\Contracts\Mapper\MapperInterface;

/**
 * 此sql构造器不会转译字段
 * 如:
 *   $this->select('myName'); ===> SELECT `myName` FROM ...
 * */
class Mapper extends Base implements MapperInterface
{
	protected $container;
	
	protected $pdo;
	
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
	
	public function __construct(\Neta\Injection\Container $container) 
	{
		$this->container = $container;
	}
	
	public function from($table, $p2 = null) 
	{
		$this->tableName = $table;
		return $this;
	}
	
	public function where($p1, $p2 = '=', $p3 = null, $table = null) 
	{
		$tb = $table ? $table : $this->tableName;

		$this->whereHandler($this->wheres, $tb, $p1, $p2, $p3, $this->whereData);
		return $this;
	}
	
	public function manyToMany($mid, $relate)
	{
		$tmp = "`$mid`";
		$this->leftJoin($mid, "$tmp.{$this->tableName}_id", 'id')
			 ->leftJoin($relate, "$tmp.{$relate}_id", "`$relate`.id");
		return $this; 
	}
	
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
	 * 跟上面belongsTo刚好相反
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
	 * @param array $prepareData 用于保存pdo预处理数据
	 * */
	protected function whereHandler(& $data, $TB, & $p1, $p2 = null, $p3 = null, & $prepareData, $autoAddTable = true)
	{
		$table = '';
		if ($autoAddTable) {
			$table = "`$TB`.";
		}
		// ---------------------------------------------------------------
		// | 第一个参数是数组
		//----------------------------------------------------------------
		if (is_array($p1)) {
			foreach ($p1 as $field => $val) {
				//$field = $this->changeToUnderlineOne($field);
				$defaultOperator = '=';
	
				foreach (self::$comparisonOperators as $k => $v) {
					if (strrpos($field, $k) !== false && ! is_array($val)) {//默认为 =
						$this->getWhereField($table, $field, rtrim($field, $k));
	
						$data[] = "{$field}{$v} ? ";
	
						$prepareData[] = $val;
						
						$defaultOperator = '';
						break;
						//**********************************************LIKE字句*************
					} elseif (strrpos($field, '%*%') !== false) {
						$this->getWhereField($table, $field, rtrim($field, '%*%'));
						
						$data[] = "$field LIKE ? ";
	
						$prepareData[] = "%$val%";
	
						$defaultOperator = '';
						break;
					} elseif (strrpos($field, '%*') !== false) {
						$this->getWhereField($table, $field, rtrim($field, '%*'));
						
						$data[] = "$field LIKE ? ";
	
						$prepareData[] = "%$val";
	
						$defaultOperator = '';
						break;
					} elseif ( strrpos($field, '*%') !== false) {
						$this->getWhereField($table, $field, rtrim($field, '*%'));
						
						$data[] = "$field LIKE ? ";
	
						$prepareData[] = "$val%";
	
						$defaultOperator = '';
						break;
						//*******************************************AND 字句嵌套 OR字句	(最多只嵌套一层)***********
					} elseif (strrpos($field, '[or]') !== false) {
						$ors = [];
						$this->whereHandler($ors, $TB, $val, null, null, $prepareData, $autoAddTable);
						$data[] = '( ' . implode( ' OR ', $ors)  . ' )';
	
						$defaultOperator = '';
						break;
					}
					//*******************************************IN字句***********
					elseif ( strrpos($field, '[in]') !== false) {
						$this->whereData = array_merge($this->whereData, $val);
							
						$this->getWhereField($table, $field, rtrim($field, '[in]'));
	
						foreach ($val as & $_) {
							$_ = '?';
						}
	
						$data[] = $field . ' IN (' . implode(',', $val)  . ')';
	
						$defaultOperator = '';
						break;
					}
	
	
				}
	
				if ($defaultOperator && ! is_array($val)) {
					$this->getWhereField($table, $field, $field);
					$data[] = $field . '=?';
						
					$prepareData[] = $val;
				}
			}
		} else {
			// ---------------------------------------------------------------
			// | 有2个参数
			//----------------------------------------------------------------
			if (! $p3) {
				$prepareData[] = $p2;
				$this->getWhereField($table, $p1, $p1);
				$data[] = $p1 . '=?';
			} else {
				// ---------------------------------------------------------------
				// | 有3个参数
				//----------------------------------------------------------------
				switch (strtolower($p2)) {
					case 'in':
					case '[in]':
						$this->whereData = array_merge($this->whereData, $p3);
						foreach ($p3 as & $_3) {
							$_3 = '?';
						}
						$this->getWhereField($table, $p1, $p1);
						$data[] = $p1 . ' IN (' . implode( ',', $p3) . ')';
						break;
					case 'or':
					case '[or]':
						$ors = [];
						$this->whereHandler($ors, $TB, $p3, null, null, $prepareData, $autoAddTable);
						$data[] = '( ' . implode( ' OR ', $ors)  . ' )';
	
						$defaultOperator = '';
						break;
					case '%*%'://LIKE 'ANY%'
						$prepareData[] = "%$p3%";
						$this->getWhereField($table, $p1, $p1);
						$data[] = "$p1 LIKE ? ";
						break;
					case '%*'://LIKE '%ANY'
						$prepareData[] = "%$p3";
						$this->getWhereField($table, $p1, $p1);
						$data[] = "$p1 LIKE ? ";
						break;
					case '*%'://LIKE '%ANY%'
						$prepareData[] = "$p3%";
						$this->getWhereField($table, $p1, $p1);
						$data[] = "$p1 LIKE ? ";
						break;
					default:
						$prepareData[] = $p3;
						$this->getWhereField($table, $p1, $p1);
						$data[] = $p1 . ' ' . $p2 . ' ?';
						break;
				}
	
					
			}
		}
	}
	
	protected function getWhereField(& $table, & $field, $change)
	{
		if (strpos($field, '.') === false) {
			$field = $table . '`' . $change . '`';
		} else {
			$field = $change;
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
	 * 读取的字段
	 * 传入：
	 * [
			'name', 'age', 'agent' => 'created_at'
		];
	 * 返回：
	 *  `user`.`name`,`user`.`age`,`agent` AS `created_at`	
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
				
			} elseif (strpos($data, ' AS ') !== false || strpos($data, ' as ') !== false || strpos($data, '.') !== false) {
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
					}//$v是数组, $k是表名
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

		$res = $this->pdo()->dbGetRow($sql, $this->whereData);
		
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

		$res = $this->pdo()->dbGetAll($sql, $this->whereData);
		
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
				//$field = $this->changeToUnderlineOne($field);
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
		
	public function leftJoin($table, $p1 = null, $p2 = null)
	{
		if (strpos($table, ' AS ') === false) {
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
		$res = $this->pdo()->delete($this->tableName, $where, $this->whereData);
		$this->clear();
		return $res;
	}
	
	public function insert(array & $p1) 
	{
		$res = $this->pdo()->add($this->tableName, $p1);
		$this->clear();
		return $res;
	}
	
	public function update($p1, $p2 = null, $p3 = null) 
	{
		if ($p2) {
			switch ($p2) {
				case '+':
					$p1 = ["`$p1` = `$p1` +" => $p3];
					break;
				case '-':
					$p1 = ["`$p1` = `$p1` -" => $p3];
					break;
			}
				
		}
		
		$where = '';
		
		$this->getWhereSql($where);
		
		$res = $this->pdo()->update($this->tableName, $p1, $where, $this->whereData);
		$this->clear();
		return $res;
	}
	
	public function insertBulk() 
	{
		
	}
	
}
