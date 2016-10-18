<?php
namespace NetaServer\Basis\Model;

use \NetaServer\Exceptions\InternalServerError;

class Base extends \NetaServer\ORM\Entity
{
	/**
	 * 查询某条数据是否存在
	 * */
	final public function exists($id = null)
	{
		$this->__id = $id ?: $this->__id;
		if (! $this->__id) {
			return false;
		}
		return $this->query()->select('id')->where('id', $this->__id)->readRow();
	}

	/**
	 * 查找字段, 先到实体对象里面找, 找不到再去数据库找
	 * @param $data string|array 要查找的字段
	 * return array
	 * */
	final public function find($data)
	{
		if (! $this->__id) {
			return false;
		}

		$find = [];
		foreach ((array) $data as $k => & $field) {
			if ($field == 'id') {
				$find['id'] = $this->__id;
				unset($data[$k]);
				continue;
			}
			if (! isset($this->__valuesContainer[$field])) {
				continue;
			}
			$find[$field] = $this->__valuesContainer[$field];
			unset($data[$k]);
		}

		if (count($data) < 1) {//全部找到
			return $find;
		}

		$result = $this->query()->select($data)->where('id', $this->__id)->readRow();
		if ($result) {
			$this->set($result);

			$find = array_merge($find, $result);
		}
		return $find;
	}

	/**
	 * 保存实体
	 * */
	final public function save()
	{
		$id = $this->get('id');
		if (! $id) {
			throw new InternalServerError('缺少id, 保存数据失败');
		}

		$data = $this->toArray();

		$this->beforeUpdate($id, $data);

		$res = $this->query()->where('id', $id)->update($data);
		if ($res) {
			$this->afterUpdate($id, $data);
		}

		$this->setIsSaved($res);

		return $res;
	}

	/**
	 * 新增方法
	 * */
	final public function add()
	{
		$this->setIsNew(true);

		$this->createHandler();

		$data = $this->toArray();

		$this->beforeCreate($data);

		$res = $this->query()->insert($data);
		if ($this->isIncrId()) {//自增Id
			$this->set('id', $res);
		}

		if ($res) {
			$this->afterCreate($data);
		}

		$this->setIsSaved($res);

		return $res;
	}

	protected function beforeCreate(array & $data)
	{

	}

	protected function afterCreate(array & $data)
	{

	}

	protected function beforeUpdate($id, array & $data)
	{

	}
	protected function afterUpdate($id, array & $data)
	{

	}

	protected function beforeRemove($id)
	{

	}

	protected function afterRemove($id)
	{

	}

	/**
	 * id table
	 * */
	final public function remove($id = null)
	{
		$this->id = $id ?: $this->id;

		if (! $id = $this->id) {
			throw new \InvalidArgumentException('缺少id删除数据失败');
		}

		$this->beforeRemove($id);
		$res = $this->query()->remove($id);
		if ($res) {
			$this->afterRemove($id);
		}
		return $res;
	}


	protected function pdo(array $config = null)
	{
		if ($config === null) {
			return $this->__container->make('pdo');
		}
		return new \NetaServer\ORM\DB\PDO($this->getConfig(), $config);
	}

	/**
	 * 实体和数据库表之间的映射管理者（对接数据库）
	 * */
	protected function query()
	{
		return $this->__container->make('query')->from($this->entityTableName);
	}

	protected function getConfig()
	{
		return $this->__container->make('config');
	}

	protected function cache()
	{
		return redis();
	}

	protected function logger()
	{
		return $this->__container->make('logger');
	}

	protected function getPasswordHash()
	{
		return $this->__container->make('passwordHash');
	}

}
