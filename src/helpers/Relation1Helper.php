<?php

namespace yii2lab\domain\helpers;

use yii2mod\helpers\ArrayHelper;
use yii2lab\domain\BaseEntity;
use yii2lab\domain\data\ArrayIterator;
use yii2lab\domain\data\Query;
use yii2lab\domain\enums\RelationEnum;

class Relation1Helper {
	
	public static function load($domain, $id, $with, $data) {
		// todo: формировать запрос with для получения дочерних связей
		$relations = RelationRepositoryHelper::getRelationsConfig($domain, $id);
		$withParams = RelationWithHelper::fetch($with, $remainOfWith);
		foreach($withParams as $relationName) {
			$relationConfig = $relations[$relationName];
			$data = self::loadRelation($data, $relationConfig, $relationName, $remainOfWith);
		}
		return $data;
	}
	
	private static function loadRelation($data, $relationConfig, $relationName, $remainOfWith) {
		if(self::isEntity($data)) {
			$data->{$relationName} = self::getRelationData($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $data, $relationConfig);
			if(!empty($remainOfWith[$relationName])) {
				self::load($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $remainOfWith[$relationName], $data->{$relationName});
			}
		} else {
			$relCollection = self::getRelationCollection($data, $relationConfig);
			foreach($data as &$item) {
				$item = self::loadRelationItem($item, $relationConfig, $relationName, $remainOfWith, $relCollection);
				if(!empty($remainOfWith[$relationName])) {
					self::load($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $remainOfWith[$relationName], $item->{$relationName});
				}
			}
		}
		return $data;
	}
	
	private static function loadRelationItem($item, $relationConfig, $relationName, $remainOfWith, $relCollection) {
		$fieldValue = $item->{$relationConfig['field']};
		if($relationConfig['type'] == RelationEnum::ONE) {
			$item->{$relationName} = $relCollection[$fieldValue];
		} else {
			$query = Query::forge();
			$query->where($relationConfig['foreign']['field'], $fieldValue);
			$item->{$relationName} = ArrayIterator::allFromArray($query, $relCollection);
			if(!empty($remainOfWith[$relationName])) {
				$item->{$relationName} = self::load($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $remainOfWith[$relationName], $item->{$relationName});
			}
		}
		return $item;
	}
	
	private static function getRelationData($domain, $id, $data, $relationConfig) {
		$query = self::forgeQuery($data, $relationConfig);
		if($relationConfig['type'] == RelationEnum::MANY) {
			return RelationRepositoryHelper::getAll($domain, $id, $query);
		} else {
			return RelationRepositoryHelper::getOne($domain, $id, $query);
		}
	}
	
	private static function forgeQuery($collection, $relationConfig) {
		$whereValue = self::getColumn($collection, $relationConfig['field']);
		$query = Query::forge();
		$query->where($relationConfig['foreign']['field'], $whereValue);
		return $query;
	}
	
	private static function getRelationCollection($data, $relationConfig) {
		$query = self::forgeQuery($data, $relationConfig);
		$relCollection = RelationRepositoryHelper::getAll($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $query);
		if($relationConfig['type'] == RelationEnum::ONE) {
			$relCollection = ArrayHelper::index($relCollection, $relationConfig['foreign']['field']);
		} else {
			$relCollection = ArrayHelper::index($relCollection, $relationConfig['field']);
		}
		return $relCollection;
	}
	
	private static function getColumn($data, $field) {
		if(self::isEntity($data)) {
			return $data->{$field};
		} else {
			$in = ArrayHelper::getColumn($data, $field);
			$in = array_unique($in);
			$in = array_values($in);
			return $in;
		}
	}
	
	private static function isEntity($data) {
		return is_object($data) && $data instanceof BaseEntity;
	}
	
}