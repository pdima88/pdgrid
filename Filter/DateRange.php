<?php

namespace pdgrid\Filter;

use pdgrid\Filter;
use pdima88\phpassets\Assets;
use Nette\Utils\Html;
use pdima88\twbshelper\Glyphicon;

class DateRange extends Filter {

    protected $_start = null;
    protected $_end = null;
    static protected $_index = 0;
    protected $_id;
    protected $_opens = 'right';

    public function init(&$column)
    {
        $this->_id = 'pdgrid_filter_daterange_'.(++self::$_index);
        if ($this->hasRequestParam()) {
            $this->_value = $this->getRequestParam();
        }
        if (isset($column['filter-opens'])) $this->_opens = $column['filter-opens'];
        if (isset($column['filterOpens'])) $this->_opens = $column['filterOpens'];

        Assets::add([
            'bootstrap-daterangepicker',
            '/assets/pdgrid/js/dateutils.js'
        ]);
    }

    function renderFilter()
    {
        $span =  Html::el('span')->style('font-size: 11px;');
        $span->data('filter', $this->_name);
        $span->data('empty', '');
        $div = Html::el('div')->id($this->_id)
                ->setClass('form-control input-sm pdgrid-filter-daterange')
                ->style('overflow: hidden; cursor: pointer; text-overflow: ellipsis;white-space: nowrap')
                //->style('background: #fff;  padding: 5px 10px; border: 1px solid #ccc; width: 100%')
                ->addHtml(
                    Glyphicon::calendar)
                ->addHtml(
                    Html::el('b')->setClass('caret')->style('float: right; margin-top: 7px'))
                ->addHtml($span);
        $input = Html::el('input')->type('hidden')->name('filter_'.$this->_name);
        $input->data('filter', $this->_name);
        $input->id($this->_id.'_input');
        if ($this->isActive()) {
            if ($this->_value == 'NULL' || $this->_value == 'NOT NULL') {
                $input->value($this->_value);
            } else {
                $r = $this->getRange();
                if ($r[0] == $r[1]) {
                    $input->value(date('d.m.Y', $r[0]));
                } else {
                    $input->value(date('d.m.Y', $r[0]) . '-' . date('d.m.Y', $r[1]));
                }
            }
        }

        $this->_grid->assets->script('
            $(function() {
                moment.locale("ru");
                $("#'.$this->_id.'").daterangepicker({
                    ranges: {
                       "Пустые": "NULL",
                       "Любая дата": "NOT NULL", 
                       "Сегодня": [moment(), moment()],
                       "Вчера": [moment().subtract(1, \'days\'), moment().subtract(1, \'days\')],
                       "Последние 7 дней": [moment().subtract(6, \'days\'), moment()],
                       "Последние 30 дней": [moment().subtract(29, \'days\'), moment()],
                       "Этот месяц": [moment().startOf(\'month\'), moment().endOf(\'month\')],
                       "Прошлый месяц": [moment().subtract(1, \'month\').startOf(\'month\'), moment().subtract(1, \'month\').endOf(\'month\')],
                       "Этот год": [moment().startOf(\'year\'), moment().endOf(\'year\')],                       
                    },
                    locale: {
                        format: "DD.MM.YYYY",
                        separator: " - ",
                        applyLabel: "Применить",
                        cancelLabel: "Сброс",
                        weekLabel: "W",
                        daysOfWeek: moment.weekdaysMin(),
                        monthNames: moment.months(),
                        firstDay: moment.localeData().firstDayOfWeek()
                    },'.
            (($this->isActive() && $this->_value != 'NULL' && $this->_value != 'NOT NULL')
                ? 'startDate: "'.date('d.m.Y', $r[0]).'",
                endDate: "'.date('d.m.Y',$r[1]).'", ' : '').'
                    showDropdowns: true,
                    linkedCalendars: false,
                    alwaysShowCalendars: true,
                    showCustomRangeLabel: false,
                    opens: "'.$this->_opens.'",
                }).on("apply.daterangepicker", function(ev, picker) {
                    var range = picker.selRange;
                    var v = range;
                    if (!range || $.isArray(range)) {
                        var start = moment(picker.startDate).format("L");
                        var end = moment(picker.endDate).format("L");
                        picker.oldStartDate = picker.startDate;
                        picker.oldEndDate = picker.endDate;
                        v = start;
                        if (start != end) v += "-"+end;
                    }
                    $("#'.$this->_id.'_input").val(v);
                    var $el = $("#'.$this->_id.' span");//.text(v);
                    var txt = "";
                    if (v == "NULL") {
                        txt = "Пустые";
                    } else if (v == "NOT NULL") {
                        txt = "Любая дата";
                    } else {
                        txt = pdgridFormatDateRange(picker.startDate, picker.endDate);
                    }    
                    $el.text(txt).attr("title", txt);
                    $.pdgrid.filter($el);
                                        
                }).on("show.daterangepicker", function(ev, picker) {
                    picker.startDate = picker.oldStartDate;
                    picker.endDate = picker.oldEndDate;
                }).on("cancel.daterangepicker", function() {
                    $("#'.$this->_id.'_input").val("");
                    $("#'.$this->_id.' span").text("").attr("title", "");
                    $.pdgrid.filter(this);
                });
                
                if ($("#'.$this->_id.'_input").val() != "") {
                    var v = $("#'.$this->_id.'_input").val();
                    var txt = "";
                    if (v == "NULL") {
                        txt = "Пустые";
                    } else if (v == "NOT NULL") {
                        txt = "Любая дата";
                    } else {
                        var m = v.split("-");
    
                        var startDate = m[0];
                        if (m.length == 1) {                      
                          var endDate = m[0];
                        } else {
                          var endDate = m[1];
                        }
                        startDate = moment(startDate,"DD.MM.YYYY");
                        endDate = moment(endDate,"DD.MM.YYYY");
                        txt = pdgridFormatDateRange(startDate, endDate);
                    }
                    $("#'.$this->_id.' span").text(txt).attr("title", txt);
                }
            });
        ',$this->_id);

        return strval($div).$input;
    }

    function getRange() {
        $values = explode('-',$this->_value);
        if (count($values) == 1) {
            $startDate = strtotime($values[0]);
            return [
                $startDate, $startDate
            ];
        } else {
            $startDate = strtotime($values[0]);
            $endDate = strtotime($values[1]);
            return [
                $startDate, $endDate
            ];
        }
    }

    function getWhere()
    {
        if ($this->isActive()) {
            $db = \Zend_Db_Table::getDefaultAdapter();

            if ($this->_value == 'NULL') {
                return $db->quoteIdentifier($this->_name) .
                ' IS NULL OR '.$db->quoteIdentifier($this->_name).' = \'0000-00-00 00:00:00\'';
            } elseif ($this->_value == 'NOT NULL') {
                return $db->quoteIdentifier($this->_name) .
                ' IS NOT NULL AND '.$db->quoteIdentifier($this->_name).' <> \'0000-00-00 00:00:00\'';
            } else {
                list($startDate, $endDate) = $this->getRange();

                $start = date('Y-m-d', $startDate) . ' 00:00:00';
                $end = date('Y-m-d', $endDate) . ' 23:59:59';

                return $db->quoteIdentifier($this->_name) .
                ' BETWEEN ' . $db->quote($start) . ' AND ' . $db->quote($end);
            }
        }
        return '';
    }
}