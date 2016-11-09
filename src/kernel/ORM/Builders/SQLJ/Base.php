<?php
namespace NetaServer\ORM\Builders\SQLJ;

use NetaServer\ORM\DB\PDO;

class Base 
{
    protected $defaultConnectionType = 'mysql';
    
    protected $connectionType = 'mysql';
    
    protected $connections = [];
    
	
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
        
        $this->connectionType = $this->defaultConnectionType;
    }
	
    protected function getGroupBySql(& $groupBy) 
    {
        $groupBy = $this->groupBy;
    }
	
	/**
	 * 设置连接数据库类型方法
	 *
	 * @date   2016-11-8 上午10:11:26
	 * @author jqh
	 * @param  string $type
	 * @return
	 */
	public function setConnection($type = 'mysql')
	{
		$this->connectionType = $type;
		return $this;
	}
	
    public function getConnection() 
    {
	    if (isset($this->connections[$this->connectionType])) {
	    	return $this->connections[$this->connectionType];
	    }
	    switch ($this->connectionType) {
	    	case $this->defaultConnectionType:
		        $this->connections[$this->defaultConnectionType] = $this->container->make('pdo');
		        break;
	    	default:
	    	    $this->connections[$this->connectionType] = new PDO(C('db.' . $this->connectionType));
	    	    break;  
	    }
	    
	    return $this->connections[$this->connectionType];
    }
}
