<?php
namespace JQH\Contracts\Mapper;
/**
 * 映射器接口
 * */
interface MapperInterface 
{
	/**
	 * 选择数据表
	 * 
	 * $this->from('User);
	 * */
	public function from($p1);
	/**
	 * 设置sql where and字句
	 * 用法示例: 
	 *  $this->where();
	 * 
	 * 注意：当查询方式为"IN"时要用"[in]", "OR"要用"[or]", "LIKE"用"%*%, %*, *%"
	 * 当$p1是数组时*********************
	 * 传入: [
		 'filedA' => 1,
		 'filedB*%' => 3,
		 'filedC%*' => 3,
		 'filedD%*%' => 4,
		 'filed_f>' => 5,
		 	
		 '1[or]' => [
			 'sun_a>=' => 'a',
			 'sun_b<'  => '3'
		 ],
	 ];
	 * 	将转化为如下sql语句：
		 `filed_a`="1" AND `filed_b` LIKE "2%" AND `filed_c` LIKE "%3" AND `filed_d` LIKE "%4%"
		 AND `filed_f`>"5" AND ( `sun_a`>="a" OR `sun_b`<"3" )
		 
	 * ******************************
	 * 当p1 p2是字符串，p3为空时
	 * p1 = field, p2 = 1,  将转为  field="1"
	 * *********************************
	 * 当p1 p2 p3均为字符串且不为空时
	 * p1是字段名, p2是查询方式, p3则是查询的字段值
	 * 注意：
	 LIKE模糊查询扔用 *%, %*%的方式，如：where( 'field', '%*', '3)  ===>  'field LIKE "%3"'
	 *      IN查询，如：where( 'field', 'IN', [1, 2])   ===> 'field IN ("1", "2")' 或 where( 'field', '[in]', [1, 2])也行
	 *      其他的查询方式和mysql原生的一样
	 * */
	public function where($p1, $p2, $p3 = null, $table = null);
	
	/**
	 * 同where用法
	 * */
	public function orWhere($p1, $p2, $p3 = null, $table = null);
	
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
	 * 注意：如要连表查数据则需要在entity类定义好相应的映射关系
	 * */
	public function select($data);
	
	public function sort($order, $desc = true);
	
	/**
	 * 读取单行数据
	 * */
	public function readRow();
	
	public function group($data);
	
	/**
	 * 读取多行数据
	 * */
	public function read();
	
    /**
     * 用法:
     *  $this->limit(0, 5); ===> LIMIT 0, 5
     *  
     *  $this->limit(5);    ===> LIMIT 5
     * */
	public function limit($p1, $p2 = 0);
	
	/**
	 * 传入：
	 * $this->leftJoin('menu_content AS u', 'u.id', 'menu_content_id')
		 	->leftJoin('wechat_menu_type AS w', 'u.wechat_menu_type_id', 'w.id')
	 * 
	 * 返回：
	   LEFT JOIN `menu_content` AS u    ON `table`.`menu_content_id`      = `u`.`id` 
	   LEFT JOIN `wechat_menu_type` AS w ON `u`.`wechat_menu_type_id` = `w`.`id`
	 * */
	public function leftJoin($data, $p1 = null, $p2 = null);
	
	public function remove($id = null);
	
	public function insert(array & $p1);
	
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
	public function update($p1, $p2 = null, $p3 = null);
	
	public function insertBulk();
}
