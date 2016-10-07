<?php
namespace JQH\Contracts\Entity;

/**
 * 实体类接口
 * */
interface EntityInterface 
{
	/**
	 * 设置字段值
	 * */
	public function set($p1, $p2);
	/**
	 * 从对象中获取字段值（不会查库）
	 * */
	public function get($name);
	/**
	 * 保存此对象数据到数据库（会自动判断新增还是修改）, 返回当前对象
	 * */
	public function save();
	/**
	 * 查找字段, 先到实体对象里面找, 找不到再去数据库找, 找到后会保存到实体对象
	 * @param $data string|array 要查找的字段
	 * return array
	 * */
	public function find($data);
	/**
	 * 清除某个字段值
	 * */
	public function clear($name);
	/**
	 * 重置保存在对象上的字段值
	 * */
	public function reset();
	/**
	 * 是否为自增id, 是返回true
	 * */
	public function isIncrId();
	/**
	 * 是否为新数据
	 * */
	public function isNew();
	
	public function setIsNew($isNew);
	/**
	 * 是否保存成功
	 * */
	public function isSaved();
	
	public function setIsSaved($isSaved);
	
	public function setValue(array $data);
	/**
	 * 是否存在公共字段(即所有表共同有的字段)
	 * */
	public function hasPublicField($field);
	/**
     * 转化为数组
     * @param $setup bool, 则表示字段再输出之前会做特殊处理（一般用于入库前操作）, 如不需特殊处理则传false（如从库中查出来，一般是已经处理过的）
     * */
	public function toArray($setup = true);
}
