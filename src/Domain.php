<?php

namespace yii2lab\domain;

use yii2lab\domain\factories\Factory;
use Yii;
use yii\base\Object as YiiObject;
use yii\base\UnknownPropertyException;
use yii2lab\domain\locators\Base;
use yii2lab\domain\repositories\BaseRepository;

/**
 * Class Domain
 *
 * @package yii2lab\domain
 *
 * @property Factory $factory
 * @property Base $repositories
 * @property Base $services
 */
class Domain extends YiiObject {
	
	private $repositoryLocator = [];
	private $serviceLocator = [];
	private $_factory;
	public $id;
	public $path;
	public $defaultDriver = 'ar';
	
	public function init() {
		$this->initPath();
		$this->initId();
	}
	
	public function __get($name) {
		try {
			$value = parent::__get($name);
			return $value;
		} catch(UnknownPropertyException $e) {
			return $this->serviceLocator->{$name};
		}
	}
	
	public function getFactory() {
		$this->init();
		if(!isset($this->_factory)) {
			$this->_factory = Yii::createObject(Factory::className());
			$attributes = [
				'id' => $this->id,
				'domain' => $this,
			];
			Yii::configure($this->_factory, $attributes);
		}
		return $this->_factory;
	}
	
	public function setServices($components) {
		$this->serviceLocator =
			$this->
			getFactory()->
			serviceLocator->
			create($this->id, $components);
	}
	
	public function getServices() {
		return $this->serviceLocator;
	}
	
	public function setRepositories($components) {
		$this->repositoryLocator =
			$this->
			getFactory()->
			repositoryLocator->
			create($this->id, $components);
	}
	
	public function getRepositories() {
		return $this->repositoryLocator;
	}
	
	private function initPath() {
		if(!empty($this->path)) {
			return;
		}
		if(!$this->isBaseClassName()) {
			$this->path = dirname(static::className());
		}
	}
	
	private function initId() {
		if(!empty($this->id)) {
			return;
		}
		$basename = basename($this->path);
		if(!$this->isBaseClassName() && $basename == 'Domain') {
			$dirname = dirname($this->path);
			$basename = basename($dirname);
		}
		$this->id = strtolower($basename);
	}
	
	private function isBaseClassName() {
		return static::className() == Domain::className();
	}
	
}