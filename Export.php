<?php

namespace pdgrid;

abstract class Export
{
    protected $_grid = null;

    static function create($type, $grid) {
        if (isset(Grid::$export[$type]) &&
            class_exists(Grid::$export[$type])) {
            $className = Grid::$export[$type];
        } elseif (class_exists($type)) {
            $className = $type;
        } else {
            throw new \Exception('Export '.$type.' not defined!');
        }
        $export = new $className($grid);
        return $export;
    }

    protected function __construct($grid) {
        $this->_grid = $grid;
    }

    abstract public function export($filename, $title, $data);
}