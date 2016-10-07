<?php
namespace NetaServer\ORM;

use \NetaServer\Injection\Container;

abstract class Entity implements \NetaServer\Contracts\Entity\EntityInterface 
{
	const BELONGS_TO   	   = 'BELONGS_TO';
	const HAS_MANY         = 'HAS_MANY';
	const BELONGS_TO_MANY  = 'BELONGS_TO_MANY';//多对多
	const HAS_MANY_THROUGH = 'HAS_MANY_THROUGH';//跨表一对多
	
	/**
	 * 公共字段[如果某些模块没有这些字段，则在实体类里面重新定义此属性]
	 * */
	protected $__publicField = [];
	
	protected $__id = null;
	/**
	 * 是否自增id，默认为true
	 * */
	protected $__isIncrId = true;

    protected $__isNew = false;

    protected $__isSaved = false;

    /**
     * Entity type.
     * @var string
     */
    protected $entityType;

    /**
     * Entity table name.
     * @var string
     */
    protected $entityTableName = null;

    /**
     * @var array Defenition of fields.
     * @todo make protected
     */
    protected $__fields = [];

    /**
	 * 表映射关系定义，大小写敏感！
	 * */
    protected static $relations = [];

    /**
     * @var array Field-Value pairs.
     */
    protected $__valuesContainer = [];

    protected $__container;

	public function __construct($name, Container $container) 
	{
		$this->entityType  	   = $name;
		$this->entityTableName = get_db_field($name);
		$this->__container 	   = $container;
	}
	
	protected function createHandler()
	{
		
	}
    
    public static function _getRelations() 
    {
    	return static::$relations;
    }
    
    /**
     * 过滤无效字段
     * */
    protected function setValueContainerHandler(& $data) 
    {
    	if(isset($data['id'])) {
    		$this->__id = $data['id'];
    		unset($data['id']);
    	}
    	if(count($this->__fields) > 0) {
    		foreach ($data as $field => & $val) {
    			if (! isset($this->__fields[$field])) {
    				unset($data[$field]);
    			}
    		}
    	}
    	
    	$this->__valuesContainer = $data;
    }
    
    /**
     * 此接口不验证数据
     * */
    final public function setValue(array $data) 
    {
    	$this->__valuesContainer = $data;
    }

    final public function clear($name = null) 
    {
        if (is_null($name)) {
            $this->reset();
        }
        unset($this->__valuesContainer[$name]);
    }
	
    /**
     * 重置
     * */
    final public function reset() 
    {
        $this->__valuesContainer = [];
    }
    
    /**
     * 是否为自增id
     * */
    final public function isIncrId() 
    {
    	return $this->__isIncrId;
    }

	/**
	 * 设置字段值
	 * */
    final public function set($p1, $p2 = null) 
    {
		if (is_array($p1)) {
			foreach ($p1 as $k => & $v) {
				if ($k == 'id') {
					$this->__id = $v;
					continue;
				}
				$this->__valuesContainer[$k] = $v;
			}
		} else {
			if ($p1 == 'id') {
				$this->__id = $p2;
				return true;
			}
			$this->__valuesContainer[$p1] = $p2;
		}
    }
	
    /**
     * 获取字段值
     * */
    final public function get($name, $params = []) 
    {
        if ($name == 'id') {
            return $this->__id;
        }

        if (isset($this->__valuesContainer[$name])) {
            return $this->__valuesContainer[$name];
        }

        return null;
    }

    final public function has($name) 
    {
        if ($name == 'id') {
            return isset($this->__id);
        }
//         $method = '_has' . ucfirst($name);
//         if (method_exists($this, $method)) {
//             return $this->$method();
//         }

        if (array_key_exists($name, $this->__valuesContainer)) {
            return true;
        }
        return false;
    }



    final public function isNew() 
    {
        return $this->__isNew;
    }

    final public function setIsNew($isNew) 
    {
        $this->__isNew = $isNew;
        if ($this->__id) {
        	return $this;
        }
        if (! $this->isIncrId()) {
        	$this->__id = $this->createID();
        }
    }
    
    /**
     * 是否存在公共字段
     * */
    final public function hasPublicField($field) 
    {
    	if (in_array($field, $this->__publicField)) {
    		return true;
    	}
    	return false;
    }

    final public function isSaved() 
    {
        return $this->__isSaved;
    }

    final public function setIsSaved($isSaved) 
    {
        $this->__isSaved = $isSaved;
    }
    
    final public function getTableName()
    {
    	return $this->entityTableName;
    }


    final public function getEntityType() 
    {
        return $this->entityType;
    }

    final public function hasField($fieldName) 
    {
        return isset($this->__valuesContainer[$fieldName]);
    }

    public function hasRelation($relationName) 
    {
        return isset($this->relations[$relationName]);
    }
    
    /**
     * 密码加密类
     * */
    protected function getPasswordHash()
    {
    	return Container::getInstance()->get('passwordHash');
    }
    
    /**
     * 转化为数组
     * */
    final public function toArray($setup = true) 
    {
    	$data = $this->__valuesContainer;
    	
    	if ($this->__id) {
    		$data['id'] = $this->__id;
    	}
    	
        return $data;
    }

    public function getRelations() 
    {
        return static::$relations;
    }
    
    public function __get($name)
    {
    	return $this->get($name);
    }
    
    public function __set($name, $value)
    {
    	$this->set($name, $value);
    }
	
    /**
     * 生成唯一字符串
     * */
    public function createID() 
    {
    	return uniqid() . substr(md5(mt_rand() . microtime(true)), 0, 4);
    }
  
}
