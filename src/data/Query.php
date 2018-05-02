<?php

namespace yii2lab\domain\data;

use Yii;
use yii\db\Expression;
use yii2lab\domain\data\query\Rest;
use yii2lab\helpers\TypeHelper;
use yii\base\Component;
use yii2mod\helpers\ArrayHelper;

/**
 * Class Query
 *
 * @package yii2lab\domain\data
 *
 * @property Rest $rest
 */
class Query extends Component {
	
	private $query = [
		'where' => null,
		'nestedQuery' => [],
	];

	public static function forge($query = null) {
		if($query instanceof Query) {
			return $query;
		}
		return new Query();
	}
	
	public function setNestedQuery($key, Query $query) {
		$this->query['nestedQuery'][$key] = $query;
		return $this;
	}
	
	public function getNestedQuery($key) {
		return ArrayHelper::getValue($this->query, "nestedQuery.$key");
	}
	
	public function where($key, $value = null) {
		if(func_num_args() == 1) {
			$this->query['where'] = $key;
		} else {
			$this->oldWhere($key, $value);
		}
		return $this;
	}
	
	public function andWhere($condition)
	{
		if ($this->query['where'] === null) {
			$this->query['where'] = $condition;
		} else {
			$this->query['where'] = ['and', $this->query['where'], $condition];
		}
		
		return $this;
	}
	
	public function orWhere($condition)
	{
		if ($this->query['where'] === null) {
			$this->query['where'] = $condition;
		} else {
			$this->query['where'] = ['or', $this->query['where'], $condition];
		}
		
		return $this;
	}
	
	private function oldWhere($key, $value) {
		if($value === null) {
			unset($this->query['where'][ $key ]);
		} else {
			$this->query['where'][ $key ] = $value;
		}
		return $this;
	}
	
	public function removeWhere($key) {
		unset($this->query['where'][ $key ]);
	}
	
	public function whereFromCondition($condition) {
		if(empty($condition)) {
			return;
		}
		if(!empty($condition)) {
			foreach($condition as $name => $value) {
				$this->where($name, $value);
			}
		}
	}
	
	public function select($fields) {
		if($fields === null) {
			unset($this->query['select']);
			return $this;
		}
		$this->setParam($fields, 'select');
		return $this;
	}
	
	public function with($names) {
		$this->setParam($names, 'with');
		return $this;
	}
	
	public function removeWith($key) {
		if(!empty($key)) {
			unset($this->query['with'][ $key ]);
		} else {
			unset($this->query['with']);
		}
	}
	
	public function page($value) {
		if($value === null) {
			unset($this->query['page']);
			return $this;
		}
		$this->query['page'] = $value;
		return $this;
	}
	
	public function perPage($value) {
		if($value === null) {
			unset($this->query['per-page']);
			return $this;
		}
		$this->query['per-page'] = $value;
		return $this;
	}
	
	public function limit($value) {
		if($value === null) {
			unset($this->query['limit']);
			return $this;
		}
		$this->query['limit'] = $value;
		return $this;
	}
	
	public function offset($value) {
		if($value === null) {
			unset($this->query['offset']);
			return $this;
		}
		$this->query['offset'] = $value;
		return $this;
	}
	
	/**
	 * Sets the ORDER BY part of the query.
	 * @param string|array|Expression $columns the columns (and the directions) to be ordered by.
	 * Columns can be specified in either a string (e.g. `"id ASC, name DESC"`) or an array
	 * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
	 *
	 * The method will automatically quote the column names unless a column contains some parenthesis
	 * (which means the column contains a DB expression).
	 *
	 * Note that if your order-by is an expression containing commas, you should always use an array
	 * to represent the order-by information. Otherwise, the method will not be able to correctly determine
	 * the order-by columns.
	 *
	 * Since version 2.0.7, an [[Expression]] object can be passed to specify the ORDER BY part explicitly in plain SQL.
	 * @return $this the query object itself
	 * @see addOrderBy()
	 */
	public function orderBy($columns)
	{
		$this->query['order'] = $this->normalizeOrderBy($columns);
		return $this;
	}
	
	/**
	 * Adds additional ORDER BY columns to the query.
	 * @param string|array|Expression $columns the columns (and the directions) to be ordered by.
	 * Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array
	 * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
	 *
	 * The method will automatically quote the column names unless a column contains some parenthesis
	 * (which means the column contains a DB expression).
	 *
	 * Note that if your order-by is an expression containing commas, you should always use an array
	 * to represent the order-by information. Otherwise, the method will not be able to correctly determine
	 * the order-by columns.
	 *
	 * Since version 2.0.7, an [[Expression]] object can be passed to specify the ORDER BY part explicitly in plain SQL.
	 * @return $this the query object itself
	 * @see orderBy()
	 */
	public function addOrderBy($columns)
	{
		$columns = $this->normalizeOrderBy($columns);
		if ($this->query['order'] === null) {
			$this->query['order'] = $columns;
		} else {
			$this->query['order'] = array_merge($this->query['order'], $columns);
		}
		return $this;
	}
	
	/**
	 * @param     $field
	 * @param int $direction
	 *
	 * @return $this
	 *
	 * @deprecated use method addOrderBy()
	 */
	public function addOrder($field, $direction = SORT_ASC) {
		$this->query['order'][ $field ] = $direction;
		return $this;
	}
	
	public function toArray() {
		return $this->query;
	}
	
	public function hasParam($key) {
		return ArrayHelper::has($this->query, $key);
	}
	
	public function getParam($key, $type = null) {
		$value = ArrayHelper::getValue($this->query, $key);
		if(!empty($type)) {
			$value = TypeHelper::encode($value, $type);
		}
		return $value;
	}
	
	public function removeParam($key) {
		ArrayHelper::remove($this->query, $key);
	}
	
	public static function cloneForCount(Query $query = null) {
		$query = self::forge($query);
		$queryClone = self::forge();
		$queryClone->whereFromCondition($query->getParam('where'));
		return $queryClone;
	}
	
	/**
	 * @return object|Rest
	 * @throws \yii\base\InvalidConfigException
	 *
	 * @deprecated move to builder
	 */
	public function getRest() {
		/** @var Rest $instance */
		$instance = Yii::createObject(Rest::class, ['query' => $this]);
		return $instance;
	}
	
	protected function normalizeOrderBy($columns)
	{
		if ($columns instanceof Expression) {
			return [$columns];
		} elseif (is_array($columns)) {
			return $columns;
		}
		
		$columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
		$result = [];
		foreach ($columns as $column) {
			if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
				$result[$matches[1]] = strcasecmp($matches[2], 'desc') ? SORT_ASC : SORT_DESC;
			} else {
				$result[$column] = SORT_ASC;
			}
		}
		
		return $result;
	}
	
	private function setParam($fields, $nameParam) {
		if(is_array($fields)) {
			if(isset($this->query[ $nameParam ])) {
				$this->query[ $nameParam ] = ArrayHelper::merge($this->query[ $nameParam ], $fields);
			} else {
				$this->query[ $nameParam ] = $fields;
			}
		} else {
			$this->query[ $nameParam ][] = $fields;
		}
		
	}
	
}
