<?php
namespace JQH\ORM\Mappers\SQLJ;

class Base
{
	protected static $comparisonOperators = [
			'<>' => '<>',
		//'*'  => 'LIKE',//*1 LIKE 'val%'; *2 LIKE '%val'; *3 LIKE '%val%'
			'>=' => '>=',
			'<=' => '<=',
			'>'  => '>',
			'<'  => '<',
			'='  => '=',
			'IN' => 'IN'
	];

	protected function getOrderBySql(& $orderBy)
	{
		$orderBy = $this->orderBy;
	}

	protected function getLeftJoinSql(& $leftJoin)
	{
		if (count($this->leftJoin) > 0) {
			$leftJoin = implode(' ', $this->leftJoin);
		}
	}

	/**
	 * 获取where字符串
	 * */
	protected function getWhereSql(& $where, $isHaving = false)
	{
		$where  = '';
		$data   = [];
		$orData = [];

		$t = ' WHERE ';

		if ($isHaving) {
			$data   = & $this->having;
			$orData = & $this->orHaving;

			$t = ' HAVING ';
		} else {
			$data   = & $this->wheres;
			$orData = & $this->orWheres;
		}

		if (count($data) > 0) {
			$where .= implode(' AND ', $data);
		}

		if (count($orData) > 0) {
			if ($where) {
				$where .= ' OR ';
			}
			$where .= implode(' OR ', $orData);

		}

		if ($where) {
			$where = $t . $where;
		}

	}


	protected function getFieldsSql(& $fields)
	{
		if ($this->field) {
			$fields .= rtrim($this->field, ', ');
		}

		if (! $fields) {
			$fields = '* ';
		}
	}

	protected function getLimitSql(& $limit)
	{
		$limit = $this->limit;
	}

	protected function clear()
	{
		$this->tableName = null;
		$this->field     = null;
		$this->limit 	 = null;
		$this->orderBy	 = null;
		$this->groupBy	 = null;

		$this->whereData  = [];
		$this->havingData = [];
		$this->leftJoin   = [];
		$this->wheres     = [];
		$this->orWheres   = [];
		$this->having	  = [];
		$this->orHaving   = [];
	}

	protected function getGroupBySql(& $groupBy)
	{
		$groupBy = $this->groupBy;
	}



	public function pdo()
	{
		return $this->container->get('pdo');
	}
}
