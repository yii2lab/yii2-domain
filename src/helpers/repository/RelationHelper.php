<?php

namespace yii2lab\domain\helpers\repository;

use yii2lab\helpers\DomainHelper;
use yii2mod\helpers\ArrayHelper;
use yii2lab\domain\data\ArrayIterator;
use yii2lab\domain\data\Query;
use yii2lab\domain\enums\RelationEnum;

class RelationHelper {
	
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
		$isEntity = DomainHelper::isEntity($data);
		if($isEntity) {
			$data = [$data];
		}
		$relCollection = self::getRelationCollection($data, $relationConfig);
		foreach($data as &$item) {
			$item = self::loadRelationItem($item, $relationConfig, $relationName, $remainOfWith, $relCollection);
		}
		return $isEntity ? $data[0] : $data;
	}
	
	private static function loadRelationItem($item, $relationConfig, $relationName, $remainOfWith, $relCollection) {
		if($relationConfig['type'] == RelationEnum::ONE) {
			$fieldValue = $item->{$relationConfig['field']};
			$item->{$relationName} = $relCollection[$fieldValue];
			$item->{$relationName} = self::attachRelation($relationConfig, $relationName, $remainOfWith, $item->{$relationName});
		} elseif($relationConfig['type'] == RelationEnum::MANY) {
			$fieldValue = $item->{$relationConfig['field']};
			$query = Query::forge();
			$query->where($relationConfig['foreign']['field'], $fieldValue);
			$item->{$relationName} = ArrayIterator::allFromArray($query, $relCollection);
			$item->{$relationName} = self::attachRelation($relationConfig, $relationName, $remainOfWith, $item->{$relationName});
		} elseif($relationConfig['type'] == RelationEnum::MANY_TO_MANY) {
			$viaRelations = RelationRepositoryHelper::getRelationsConfig($relationConfig['via']['domain'], $relationConfig['via']['name']);
			$viaRelationToThis = $viaRelations[$relationConfig['via']['this']];
			$viaRelationToForeign = $viaRelations[$relationConfig['via']['foreign']];
			$fieldValue = $item->{$viaRelationToForeign['foreign']['field']};
			$query = Query::forge();
			$query->where($viaRelationToThis['field'], $fieldValue);
			$viaData = ArrayIterator::allFromArray($query, $relCollection);
			$foreignIds = ArrayHelper::getColumn($viaData, $viaRelationToForeign['field']);
			$query2 = Query::forge();
			$query2->where($viaRelationToForeign['foreign']['field'], $foreignIds);
			$item->{$relationName} = RelationRepositoryHelper::getAll($viaRelationToForeign['foreign']['domain'], $viaRelationToForeign['foreign']['name'], $query2);
			$item->{$relationName} = self::attachRelation($viaRelationToForeign, $relationName, $remainOfWith, $item->{$relationName});
		}
		return $item;
	}
	
	private static function attachRelation($relationConfig, $relationName, $remainOfWith, $itemAttribute) {
		if(!empty($remainOfWith[$relationName])) {
			$itemAttribute = self::load($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $remainOfWith[$relationName], $itemAttribute);
		}
		return $itemAttribute;
	}
	
	private static function getRelationCollection($data, $relationConfig) {
		$relCollection = null;
		if($relationConfig['type'] == RelationEnum::ONE) {
			$query = self::forgeQuery($data, $relationConfig);
			$relCollection = RelationRepositoryHelper::getAll($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $query);
			$relCollection = ArrayHelper::index($relCollection, $relationConfig['foreign']['field']);
		} elseif($relationConfig['type'] == RelationEnum::MANY) {
			$query = self::forgeQuery($data, $relationConfig);
			$relCollection = RelationRepositoryHelper::getAll($relationConfig['foreign']['domain'], $relationConfig['foreign']['name'], $query);
			$relCollection = ArrayHelper::index($relCollection, $relationConfig['field']);
		} elseif($relationConfig['type'] == RelationEnum::MANY_TO_MANY) {
			$viaRelations = RelationRepositoryHelper::getRelationsConfig($relationConfig['via']['domain'], $relationConfig['via']['name']);
			$viaRelationToThis = $viaRelations[$relationConfig['via']['this']];
			$ids = ArrayHelper::getColumn($data, $viaRelationToThis['foreign']['field']);
			$query = Query::forge();
			$query->where($viaRelationToThis['field'], $ids);
			$relCollection = RelationRepositoryHelper::getAll($relationConfig['via']['domain'], $relationConfig['via']['name'], $query);
		}
		return $relCollection;
	}
	
	private static function forgeQuery($collection, $relationConfig) {
		$whereValue = self::getColumn($collection, $relationConfig['field']);
		$query = Query::forge();
		$query->where($relationConfig['foreign']['field'], $whereValue);
		return $query;
	}
	
	private static function getColumn($data, $field) {
		if(DomainHelper::isEntity($data)) {
			return $data->{$field};
		} else {
			$in = ArrayHelper::getColumn($data, $field);
			$in = array_unique($in);
			$in = array_values($in);
			return $in;
		}
	}
	
}
