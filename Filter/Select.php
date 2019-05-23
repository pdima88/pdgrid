<?php

namespace pdgrid\Filter;

use pdgrid\Filter;
use Nette\Utils\Html;

class Select extends Filter
{
    protected $_options = [];
    protected $_where = null;

    public function init(&$column)
    {
        if (isset($column['filterFormat']) && is_array($column['filterFormat'])) {
            $this->_options = $column['filterFormat'];
        } elseif (isset($column['format']) && is_array($column['format'])) {
            $this->_options = $column['format'];
        } elseif (isset($column['format']) && ($column['format'] == 'checkbox')) {
            $this->_options = [
                0 => 'Нет',
                1 => 'Да'
            ];
        }

        if (isset($column['filterWhere'])) {
            $this->_where = $column['filterWhere'];
        }

        if ($this->hasRequestParam()) {
            $this->_value = $this->getRequestParam();
            if (!isset($this->_options[$this->_value])) $this->_value = null;
        }        
    }

    function renderFilter()
    {
        $select = Html::el('select')->addHtml(Html::el('option', '(Все)')->value(''))
            ->setClass("form-control input-sm")
            ->name('filter_'.$this->_name)
            ->onchange('return $.pdgrid.filter(this);');
        $select->data('filter', $this->_name);

        $selectedOption = null;

        foreach ($this->_options as $val => $option) {
            if ($val === '') $val = 'null';

            if ($this->isActive() && $this->_value == $val) {
                $selectedOption = $option;
            }

            $select->addHtml(Html::el('option', $option)->value($val)
                ->selected(isset($this->_value) && $this->_value == $val));
        }
        if ($selectedOption) {
            $select->title($selectedOption);
        }
        return $select;
    }

    function getWhere()
    {
        if ($this->isActive()) {
            if (isset($this->_where)) {
                if (is_callable($this->_where)) {
                    return call_user_func($this->_where, $this);
                } elseif (is_array($this->_where)) {
                    return isset($this->_where[$this->_value]) ? $this->_where[$this->_value]: '';
                } else {
                    return sprintf($this->_where, $this->_value);
                }
            } else {
                $db = \Zend_Db_Table::getDefaultAdapter();
                return $db->quoteIdentifier($this->_name) .
                (($this->_value == 'null') ? 'IS NULL ' : ' = ' . $db->quote($this->_value));
            }
        }
        return '';
    }
}