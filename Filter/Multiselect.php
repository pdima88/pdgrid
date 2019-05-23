<?php

namespace pdgrid\Filter;

use pdima88\phpassets\Assets;
use pdgrid\Filter;
use Nette\Utils\Html;

class Multiselect extends Filter
{
    protected $_options = [];
    static protected $_index = 0;
    protected $_where = null;
    protected $_id;

    static protected $_styleRendered = false;

    public function init(&$column)
    {
        $this->_id = 'pdgrid_filter_multiselect_'.(++self::$_index);

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

        if ($this->isActive()) {
            $v = explode(',', $this->_value);
            if (empty($v)) {
                $this->_value = null;
            } else {
                $this->_value = $v;
            }
        }
    }

    function renderFilter()
    {
        Assets::add('bootstrap-multiselect');

        $select = Html::el('select')//->addHtml(Html::el('option', '(Все)')->value(''))
            ->id($this->_id)
            ->name('filter_'.$this->_name)
            ->onchange('return $.pdgrid.filter(this);')
            ->multiple(true)
            ->data('filter', $this->_name);

        $selectedOptions = '';

        foreach ($this->_options as $val => $option) {
            if ($val === '') $val = 'null';

            if ($this->isActive() && in_array($val, $this->_value)) {
                $selectedOptions .= ($selectedOptions != '' ? ', ' : '').$option;
            }

            $select->addHtml(Html::el('option', $option)->value($val)
                ->selected($this->isActive() && in_array($val, $this->_value)));
        }
        if ($selectedOptions) {
            $select->title($selectedOptions);
        }

        $this->_grid->assets->script('$(function() {
            $("#'.$this->_id.'").multiselect({
                buttonClass: "form-control input-sm",
                numberDisplayed: 1,
                allSelectedText: "(Все)",
                nonSelectedText: "(Все)",
                nSelectedText: " выбрано",                
                templates: {
                    button: \'<button type="button" class="multiselect dropdown-toggle" data-toggle="dropdown"><b class="caret"></b>'.
            ($this->isActive() ? '<span class="form-control-clear glyphicon glyphicon-remove" filterid="'.$this->_name.'"></span>' : '').
                '<span class="multiselect-selected-text"></span> </button>\',
                }
            });
        });');

        if (!self::$_styleRendered) {
            self::$_styleRendered = true;
            Assets::addStyle('
                button.multiselect {
                    text-overflow: ellipsis;
                    overflow: hidden;
                    white-space: normal;
                }
                
                button.multiselect .caret {
                    float: right;
                    top: 7px;
                    position: relative;
                }
                
                .multiselect-selected-text {
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    overflow: hidden;
                    height: 18px;
                    display: block;
                    /*word-break: break-all;
                    display: inline-block;
                            width: 50%;
                                white-space: nowrap;*/
    text-overflow: ellipsis;
    overflow: hidden; height: 24px;
                }
                
                button.multiselect .form-control-clear {
                    float: right;
                    position: relative;
                    top: 3px;
                    right: 4px;
                }
            ');
        }

        return $select;
    }

    function getWhere()
    {
        $where = '';

        if ($this->isActive()) {
            if (isset($this->_where)) {
                if (is_callable($this->_where)) {
                    return call_user_func($this->_where, $this);
                }
            }

            foreach ($this->_value as $v) {
                $w = '';
                if (isset($this->_where)) {
                    if (is_array($this->_where)) {
                        $w = isset($this->_where[$v]) ? $this->_where[$v] : '';
                    } else {
                        return sprintf($this->_where, $v);
                    }
                } else {
                    $db = \Zend_Db_Table::getDefaultAdapter();
                    $w = $db->quoteIdentifier($this->_name) .
                    (($v == 'null') ? 'IS NULL ' : ' = ' . $db->quote($v));
                }
                if ($w != '') $where .= ($where == '' ? '' : ' OR ').$w;
            }
        }
        return $where;
    }
}