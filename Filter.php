<?php

namespace pdgrid;

abstract class Filter {
    static function create($grid, $name) {
        $column = $grid->columns[$name];
        $type = $column['filter'];
        
        if (isset(Grid::$filter[$type]) &&
            class_exists(Grid::$filter[$type])) {
            $className = Grid::$filter[$type];
        } elseif (class_exists($type)) {
            $className = $type;
        } else {
            throw new \Exception('Filter '.$type.' not defined!');
        }
        $filter = new $className($grid, $name);
        $filter->init($column);
        return $filter;
    }

    static protected $_filters = null;

    static function parseFilter($name) {
        if (!isset(self::$_filters)) {
            if (!isset($_REQUEST['filter'])) return null;
            $filterStr = $_REQUEST['filter'];
            $filterStr .= ';';

            preg_match_all('/([-\w\.]*):(["\']?)(.*?)\\2;/', $filterStr, $out);
            self::$_filters = [];
            foreach ($out[1] as $i => $key) {
                self::$_filters[$key] = stripslashes($out[3][$i]);
            }
        }
        return isset(self::$_filters[$name]) ? self::$_filters[$name] : null;
    }

    protected $_grid = null;
    protected $_name = '';
    protected $_value = null;

    protected function __construct($grid, $name) {
        $this->_grid = $grid;
        $this->_name = $name;
        $this->_value = self::parseFilter($name);
    }
    
    function init(&$column) {

    }

    function isActive() {
        return isset($this->_value);
    }

    abstract function renderFilter();

    abstract function getWhere();

    function getValue() {
        return $this->_value;
    }

    function getName() {
        return $this->_name;
    }

    function hasRequestParam($suffix = '') {
        return isset($_REQUEST['filter_'.$this->_name.(($suffix !== '') ? '_'.$suffix : '')]);
    }

    function getRequestParam($suffix = '') {
        return $_REQUEST['filter_'.$this->_name.(($suffix !== '') ? '_'.$suffix : '')];
    }
}