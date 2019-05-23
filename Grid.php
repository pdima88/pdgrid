<?php

namespace pdgrid;

use pdima88\phpassets\Assets;
use pdima88\twbshelper\Glyphicon;
use pdima88\twbshelper\TwbsBtn;
use Nette\Utils\Html;
use Nette\Utils\Arrays;

class Grid {

    const ACTION_BTN_CLASS = 'pdgrid-action-btn';

    static $filter = [
        'dateRange' => '\\pdgrid\\Filter\\DateRange',
        'daterange' => '\\pdgrid\\Filter\\DateRange',
        'date-range' => '\\pdgrid\\Filter\\DateRange',
        'equal' => '\\pdgrid\\Filter\\Equal',
        'select' => '\\pdgrid\\Filter\\Select',
        'text' => '\\pdgrid\\Filter\\Text',
        'multiselect' => '\\pdgrid\\Filter\\Multiselect'
    ];
    static $export = [
        'csv' => '\\pdgrid\\Export\\Csv',
    ];

    protected $_id = '';
    protected $_pg;
    protected $_sort = false;
    protected $_rownum = 1;
    protected $_filters = array();
    protected $_classes = array();
    protected $_sql;
    protected $_db;
    protected $_filterSql = null;
    protected $_filterParam = null;
    protected $_row = [];
    protected $_data = false;
    public $assets = null;

    public $options = [
        'ajax' => '',
        'url' => '',
        'add' => '',
        'edit' => '',
        'delete' => '',
        'ajax-delete' => '',
        'multisort' => false,
        'footer' => true,
        'rownum' => true,
        'paging' => 10,
        'export' => false,
        'export_menu_right' => true,
        'group' => false,
        'sort' => false,
        'ajaxSetUrl' => true,
        'actions' => false,
        'colgroups' => false
    ];
    public $columns = [];

    public $rows = null;

    function __construct($attr = array())
    {
        $this->assets = new Assets();
        $this->options['url'] = $_SERVER['REQUEST_URI'];
        foreach ($this->options as $key => $value) {
            if (isset($attr[$key])) {
                $this->options[$key] = $attr[$key];
            }
        }

        if (isset($attr['data'])) {
            $this->_data = $attr['data'];
        } elseif (isset($attr['select'])) {
            $sel = $attr['select'];
            $attr['sql'] = 'SELECT * FROM (' .$sel->assemble() . ') s WHERE {where}';
        }
        if (isset($attr['id'])) $this->_id = $attr['id'];

        if (isset($attr['columns']) && is_array($attr['columns'])) {
            $this->columns = $attr['columns'];
        }

        $page = Arrays::get($_REQUEST, 'page', 1);

        if ($this->_data !== false) {
            $this->_pg = Paginator::fromArray($this->_data, $this->options['paging'], $page);
        } else {
            $this->_sql = $this->applySort($this->applyFilters($attr['sql']));
            $this->_db = Arrays::get($attr, 'select', false) ?
                $attr['select']->getAdapter()
                : \Zend_Db_Table::getDefaultAdapter();
            $this->_pg = new Paginator(
                $this->_db,
                $this->_sql,
                $this->options['paging'], $page);
        }
        $this->_rownum = $this->_pg->first ?: 1;
    }

    protected $_deleteBtnTpl = null;
    protected $_editBtnTpl = null;
    protected $_actionsTpl = null;

    public function row($row)
    {
        $this->_row = $row;
        $rowid = isset($row['id']) ? $row['id'] : 0;
        $r = '';
        if ($this->options['rownum']) {
            $r .= '<th class="text-right" style="padding-left: 10px;padding-right: 10px;white-space: nowrap;width:1px">'.$this->_rownum++.'</th>';
        }
        foreach ($this->columns as $colId => &$c) {
            $r .= $this->cell($colId, $row);
        }

        $this->_editBtnRender();

        if (!isset($this->_deleteBtnTpl)) {
            if ($this->options['delete']) {
                $this->_deleteBtnTpl = TwbsBtn::dangerXs(Glyphicon::remove, self::ACTION_BTN_CLASS)
                    ->onclick("return $.pdgrid.confirmDelete(this)")
                    ->title("Удалить");
            } else {
                $this->_deleteBtnTpl = false;
            }
        }


        $this->_actionsTpl = '';
        if (is_array($this->options['actions'])) {
            foreach ($this->options['actions'] as $act => $tpl) {
                if (empty($this->_actionsTpl)) $this->_actionsTpl .= ' ';
                $this->_actionsTpl .= $tpl;
            }
        }else {
            $this->_actionsTpl = $this->options['actions'];
        }
        $this->_actionsTpl = $this->replace($this->_actionsTpl, $this->_row);

        if ($this->_actionsTpl != '' ||
            $this->_editBtnTpl ||
            $this->_deleteBtnTpl) {
            $r .= '<td style="width:1px; white-space:nowrap;">'. $this->_actionsTpl.' '.$this->_editBtnTpl .' '. $this->_deleteBtnTpl . '</td>';
        }
        return '<tr data-rowid="'.$rowid.'">'.$r.'</tr>';
    }

    protected function _editBtnRender(){
        $array = is_array($this->options['edit']);
        if (!isset($this->_editBtnTpl)) {
            if ($this->options['edit'] !== '' && $this->options['edit'] !== false) {
                $this->_editBtnTpl = TwbsBtn::a_warningXs(Glyphicon::edit, self::ACTION_BTN_CLASS)
                    ->title("Редактировать");
                if($array) {
                    foreach ($this->options['edit'] as $key=>$v){
                        $this->_editBtnTpl->{$key}($this->replace($v, $this->_row));
                    }
                }else {
                    $this->_editBtnTpl->href($this->replace($this->options['edit'], $this->_row));
                }
            } else {
                $this->_editBtnTpl = false;
            }
        } elseif ($this->_editBtnTpl !== false) {
            if($array) {
                foreach ($this->options['edit'] as $key=>$v){
                    $this->_editBtnTpl->{$key}($this->replace($v, $this->_row));
                }
            }else {
                $this->_editBtnTpl->href($this->replace($this->options['edit'], $this->_row));
            }
        }
    }

    protected function _group($group, $row, $colCount, $groupTitle = '') {
        $actions = '';
        if (isset($this->options['group']['edit']) ||
            isset($this->options['group']['delete'])) {
            $colCount --;
            $actions = Html::el('td', ['style' => 'width:1px;white-space:nowrap']);
            if (isset($this->options['group']['edit'])) {
                $actions->addHtml(
                    TwbsBtn::a_warningXs(Glyphicon::edit, self::ACTION_BTN_CLASS)
                        ->href($this->replace($this->options['group']['edit'], ['id' => $group]))
                        ->title("Редактировать")
                );
            }
            if (isset($this->options['group']['delete'])) {
                $actions->addHtml(
                    TwbsBtn::dangerXs(Glyphicon::remove, self::ACTION_BTN_CLASS)
                        ->onclick("return $.pdgrid.confirmDeleteGroup(this)")
                        ->title("Удалить")
                );
            }
        }

        $content = $groupTitle;
        $format = false;

        if (isset($this->options['group']['format'])) $format = $this->options['group']['format'];

        if (is_string($format)) {
            if ($format !== '') {
                if (is_callable($format)) {
                    $content = call_user_func($format, $group, $row);
                } else {
                    $content = sprintf($format, $group);
                }
            }
        } elseif (is_array($format)) {
            if (isset($format[$group])) {
                $content = $format[$group];
            } else {
                $content = '';
            }
        } elseif (is_callable($format)) {
            $content = $format($group);
        }

        if (is_array($row)) $content = $this->replace($content, $row);

        $tr =  Html::el('tr', ['class' => 'group'])->addHtml(
            Html::el('th')->addHtml($content)->colspan($colCount). ' ' .
            $actions
        )->data('groupid', $group);
        return $tr;
    }

    public function body() {
        $body = '';
        $rows = $this->_pg->fetchPage();
        $this->rows = $rows;

        $colCount = count($this->columns);
        if ($this->options['rownum']) $colCount++;
        if ($this->options['edit'] !== '' || $this->options['delete'] !== '' ||
            isset($this->options['actions']) && $this->options['actions']) $colCount++;

        if (empty($rows)) {
            $body = '<tr><td colspan="' . $colCount . '">Нет данных для отображения</td></tr>';
        }

        if (isset($this->options['group'])
            && isset($this->options['group']['column'])
            && isset($this->options['group']['list'])) {
            $groupCol = $this->options['group']['column'];

            $rowsByGroups = [];

            // render rows not in groups
            foreach ($rows as &$row) {
                $group = '';
                if (isset($row[$groupCol])) {
                    $group = $row[$groupCol];
                }
                if (!isset($this->options['group']['list'][$group])) {
                    $body .= $this->row($row);
                } else {
                    if (!isset($rowsByGroups[$group])) $rowsByGroups[$group] = [];
                    $rowsByGroups[$group][] = $row;
                }
            }
            unset($row);
            // render groups & rows inside groups
            foreach ($this->options['group']['list'] as $group => $groupTitle) {
                $body .= $this->_group($group, $groupTitle, $colCount, $groupTitle);
                if (!isset($rowsByGroups[$group]) || empty($rowsByGroups[$group])) {
                    $body .= '<tr><td colspan="' . $colCount . '">Нет элементов в группе</td></tr>';
                } else {
                    foreach ($rowsByGroups[$group] as &$row) {
                        $body .= $this->row($row);
                    }
                }
                unset($row);
            }
        } else {
            $group = false;
            foreach ($rows as &$row) {
                if (isset($this->options['group']) && isset($this->options['group']['column'])) {
                    $groupCol = $this->options['group']['column'];
                    if (isset($row[$groupCol])) {
                        if ($group === false || $group != $row[$groupCol]) {
                            $group = $row[$groupCol];
                            $body .= $this->_group($group, $row, $colCount);
                        }
                    }
                }
                $body .= $this->row($row);
            }
            unset($row);
        }


        return $body;
    }

    /**
     * Возвращает HTML для вывода заголовка таблицы
     * @return string
     */
    public function header()
    {
        $head = '';
        if ($this->options['rownum']) {
            $rownumTh = Html::el('th', ['style' => 'width:1px']);
            if (isset($this->options['rownum']['header']['rowspan'])) {
                $rownumTh->rowspan($this->options['rownum']['header']['rowspan']);
            }
            if (isset($this->options['rownum']['header']['before'])) {
                $head .= $this->options['rownum']['header']['before'];
            }
            $head .= $rownumTh;
            if (isset($this->options['rownum']['header']['after'])) {
                $head .= $this->options['rownum']['header']['after'];
            }

        }
        foreach ($this->columns as $colId => &$col)
        {
            $this->_prepareCol($colId);
            if (isset($col['header']['before'])) $head .= $col['header']['before'];
            if (!isset($col['header']['visible']) || $col['header']['visible']) {
                $head .= $col['th'];
            }
            if (isset($col['header']['after'])) $head .= $col['header']['after'];
        }
        unset($col);
        if ($this->options['edit'] || $this->options['delete'] || $this->options['actions']) {
            $head .= '<th style="width:1px; white-space:nowrap;"></th>';
        }

        if (!empty($this->_filters)) {
            $head .= '</tr><tr class="filters">';


            if ($this->options['rownum']) {
                $head .= '<th style="width:1px"></th>';
            }
            foreach ($this->columns as $colId => &$col)
            {
                if (isset($this->_filters[$colId]) &&
                    $this->_filters[$colId]->isActive()) {
                    $head .= '<th class="filter-active">';
                } else {
                    $head .= '<th>';
                }
                if (isset($this->_filters[$colId])) {
                    $head .= $this->_filters[$colId]->renderFilter();
                }
                $head .= '</th>';
            }
            unset($col);

            if (!$this->options['edit'] && !$this->options['delete'] && !$this->options['actions']) {
                if (substr($head, strlen($head)-9, 9) == '<th></th>') {
                    $head = substr($head,0, strlen($head)-9);
                }
            }
            //if ($this->options['edit'] || $this->options['delete']) {
                $head .= '<th style="width:1px; white-space:nowrap; vertical-align: middle">'.
                    '<button class="btn btn-info btn-xs" type="submit" onclick="return $.pdgrid.filter(this)"><i class="glyphicon glyphicon-filter"></i></button> '.
                    '<button class="btn btn-link btn-xs" type="reset" onclick="return $.pdgrid.clearFilters(this);"><i class="glyphicon glyphicon-remove"></i></button>'.
                    '</th>';
            //}

        }

        return '<tr>'.$head.'</tr>';
    }

    public function cell($colId, $row)
    {
        $this->_prepareCol($colId);
        $col = &$this->columns[$colId];
        $td = $col['td'];

        $val = isset($row[$colId]) ? $row[$colId] : null;

        $content = '{'.$colId.'}';

        if (!isset($col['format'])) $col['format'] = '';

        if (is_string($col['format'])) {
            if ($col['format'] !== '') {
                switch ($col['format']) {
                    case 'img':
                        $content = '<img src="'.(isset($col['url']) ? $col['url'] : $val).'" '.
                            (isset($col['width']) ? ' style="max-width:' . $col['width'] . 'px"' : '')
                            .'>';
                        break;
                    case 'checkbox':
                        $input = Html::el('input')->type('checkbox');
                        if ($val) $input->checked(true);
                        if (isset($col['onclick']) && $col['onclick']) {
                            $input->onclick($col['onclick']);
                        } else {
                            $input->disabled(true);
                        }
                        $content = strval($input);
                        break;
                    case 'datetimefull':
                        $content = $val ? self::formatDateTime($val, false) : '';
                        break;
                    case 'datetime':
                        $content = $val ? self::formatDateTime($val) : '';
                        break;
                    case 'date':
                        $content = $val ? self::formatDate($val) : '';
                        break;
                    /*case 'custom':
                        if (isset($col['func'])) {
                            $val = call_user_func($col['func'], $val);
                        }
                        break;*/
                    default:
                        if (is_callable($col['format'])) {
                            $content = call_user_func($col['format'], $val, $row);
                        } else {
                            $content = sprintf($col['format'], $val);
                        }
                        break;
                };
            }
        } elseif (is_array($col['format'])) {
            if (isset($col['format'][$val])) {
                $content = $col['format'][$val];
            } else {
                $content = '';
            }
        } elseif (is_callable($col['format'])) {
            $content = $col['format']($val, $row);
        }

        if ($content == '{'.$colId.'}' && $val === null) $content = '';

        $td = str_replace('%%content%%', $content, $td);
        $td = $this->replace($td, $row);

        // TODO: доделать другие типы

        return $td;
    }

    public function getFilter($colId) {
        if (!isset($this->_filters[$colId])) {
            if (isset($this->columns[$colId]) &&
                isset($this->columns[$colId]['filter'])) {
                $this->_filters[$colId] = Filter::create($this, $colId);
            }
        }
        return isset($this->_filters[$colId]) ? $this->_filters[$colId] : false;
    }

    protected function _renderSortButton($colId, $title) {
        $this->_prepareSort();
        $sort = '';
        $sort_dir = 'asc';
        $sort_order = 0;
        $icon = 'sort';
        if (isset($this->_sort[$colId])) {
            if ($this->_sort[$colId] == 'ASC') {
                $sort_dir = 'desc';
                $icon = 'triangle-top';
            } elseif ($this->_sort[$colId] == 'DESC') {
                $sort_dir = '';
                $icon = 'triangle-bottom';
            }
            $sort_order = 1;
        } else {
            $sort_dir = 'asc';
        }
        if ($sort_dir !== '') {
            $sort = $colId.':'.$sort_dir;
        }
        $i = 0;

        if ($this->options['multisort']) {
            $sort_order = 0;
            foreach ($this->_sort as $name => $dir) {
                $i++;
                if ($name == $colId) {
                    $sort_order = $i;
                } else {
                    if ($sort !== '') $sort .= ';';
                    $sort .= $name . ':' . strtolower($dir);
                }
            }
        }

        $link = Html::el('a', $title);

        $link->href = $this->url(['sort' => $sort]);
        $link->onclick = "return $.pdgrid.sort(this, '{$colId}')";
        $link->data('column-id', $colId);
        $link->setClass('pdgrid_sort');
        if ($sort_order > 0) $link->addClass('active');

        if ($icon != 'sort') $link->addHtml(' '.
             Glyphicon::icon($icon)
        );

        if ($this->options['multisort'] && $sort_order > 0 && $i > 1) {
            $link->addHtml(Html::el('sub', $sort_order));
        }

        return $link;
    }

    protected function _colHeader($colId) {
        if (!isset($this->columns[$colId])) return '';
        $col = $this->columns[$colId];
        $title = (isset($col['title']) ? $col['title'] : '');
        $th = '';

        if (isset($col['sort']) && $col['sort']) {
            $th .= $this->_renderSortButton($colId, $title);
        } else {
            $th .= $title;
        }

        return $th;
    }


    /**
     * Выполняет подготовку шаблона вывода ячейки и заголовка колонки
     * @param $colId
     */
    protected function _prepareCol($colId) {
        if (isset($this->columns[$colId]['td'])) return;
        $col = &$this->columns[$colId];
        $colClass = str_replace(',','',$colId);
        $className = 'pdgrid-'.$this->_id.'-col-'.$colClass;

        $th = Html::el('th', ['class' => $className]);
        $td = Html::el('td', ['class' => $className]);
        $tdstyle = Assets::newStyle();
        $thstyle = Assets::newStyle();

        if (isset($col['width'])) {
            //$tdstyle->width = $col['width'].'px';
            $thstyle->width = $col['width'].'px';
        }
        if (isset($col['style'])) {
            $tdstyle->add($col['style']);
        }
        if (isset($col['nowrap']) && $col['nowrap']) $tdstyle->whiteSpace = 'nowrap';
        if (isset($col['align'])) {
            $tdstyle->textAlign = $col['align'];
            $thstyle->textAlign = $col['align'];
        }
        if (isset($col['header']['rowspan'])) {
            $th->rowspan($col['header']['rowspan']);
        }

        $tdstyle->save('td.'.$className);
        $thstyle->save('th.'.$className);

        $th->addHtml($this->_colHeader($colId));

        if (isset($col['href'])) {
            $a = Html::el('a', '%%content%%')->href($col['href']);
            if (isset($col['hrefTarget'])) $a->target($col['hrefTarget']);
            $td->addHtml($a);
        } else {
            $td->addText('%%content%%');
        }
        $col['td'] = strval($td);
        $col['th'] = strval($th);
    }

    protected function _prepareSort() {
        if ($this->_sort !== false) return;
        $this->_sort = [];
        if ($this->options['group'] !== false) {
            if (isset($this->options['group']['column'])) {
                $groupCol = $this->options['group']['column'];
                $this->_sort[$groupCol] = 'ASC';
            }
        }



        if (isset($_REQUEST['sort'])) {
            $sort = explode(';',$_REQUEST['sort']);
            foreach ($sort as $s) {
                $p = explode(':',$s);
                if (count($p) > 1) {
                    $name = $p[0];
                    $dir = $p[1];
                } else {
                    $name = $s;
                    $dir = 'asc';
                }
                if (isset($this->columns[$name])) {
                    $this->_sort[$name] = (($dir == 'desc' || $dir == 'DESC') ? 'DESC' : 'ASC');
                    if (!$this->options['multisort']) break;
                }
            }
        }

        // Apply sort params for non-sortable columns
        if (is_array($this->options['sort'])) {
            foreach ($this->options['sort'] as $colId => $dir) {
                if (isset($_REQUEST['sort'])
                    && isset($this->columns[$colId]['sort'])
                ) continue;

                $this->_sort[$colId] = (($dir == 'desc' || $dir == 'DESC') ? 'DESC' : 'ASC');
            }
        }



        /*else {
            // Default sort params for sortable columns
            if (is_array($this->options['sort'])) {
                foreach ($this->options['sort'] as $colId => $dir) {
                    if (isset($this->columns[$colId]['sort'])) {
                        $this->_sort[$colId] = (($dir == 'desc' || $dir == 'DESC') ? 'DESC' : 'ASC');
                        if (!$this->options['multisort']) break;
                    }
                }
            }
        }*/
    }

    public function _defaultSort() {
        $sort = [];
        if (is_array($this->options['sort'])) {
            foreach ($this->options['sort'] as $colId => $dir) {
                if (isset($this->columns[$colId]['sort'])) {
                    $sort[$colId] = $dir;
                    if (!$this->options['multisort']) break;
                }
            }
        }
        $s = '';
        foreach ($sort as $name => $dir) {
            if ($s != '') $s .= ';';
            $s .= $name.':'.(($dir == 'desc' || $dir == 'DESC') ? 'desc' : 'asc');
        }
        return $s;
    }

    public function applySort($sql)
    {
        $this->_prepareSort();
        $sortSql = '';
        foreach ($this->_sort as $name => $dir) {
            if ($sortSql !== '') $sortSql .= ', ';
            $sortSql .= $name . ' ' . $dir;
        }
        if ($sortSql !== '') $sortSql = ' ORDER BY ' . $sortSql;
        return $sql . $sortSql;
    }

    public function applyFilters($sql) {
        if (!isset($this->_filterSql)) {
            $this->_filterSql = '';
            foreach ($this->columns as $colId => &$col) {
                $filter = $this->getFilter($colId);
                if ($filter !== false) {
                    $fSql = $filter->getWhere();
                    if ($fSql != '') {
                        if ($this->_filterSql != '') $this->_filterSql .= ' AND ';
                        $this->_filterSql .= '(' . $fSql . ')';
                    }
                }
            }
            unset ($col);
        }
        return str_replace('{where}', (($this->_filterSql == '') ? '(1=1)' : $this->_filterSql), $sql);
    }
    
    public function getFilterParam() {
        if (!isset($this->_filterParam)) {
            $this->_filterParam = '';
            foreach ($this->columns as $colId => &$col) {
                $filter = $this->getFilter($colId);
                if ($filter !== false && $filter->isActive()) {
                    $db = \Zend_Db_Table::getDefaultAdapter();
                    $v = $filter->getValue();
                    if ($this->_filterParam != '') $this->_filterParam .= ';';
                    $this->_filterParam .= $filter->getName() . ':';
                    if (is_numeric($v)) $this->_filterParam .= $v;
                    else $this->_filterParam .= $db->quote($v);
                }
            }
            unset ($col);
        }
        return $this->_filterParam;
    }

    public function render() {

        Assets::add(['bootstrap', 'eModal',
            '/assets/pdgrid/css/grid.css', '/assets/pdgrid/js/grid.js',
            '/assets/pdgrid/js/loadingoverlay.js']);

        //$table = '<div class="panel panel-default"><div class="panel-heading">'.
        //    $this->getAddBtn(). '</div>';
        $colCount = count($this->columns);
        if ($this->options['rownum']) $colCount++;
        if ($this->options['edit'] || $this->options['delete'] || $this->options['actions']) {
            $colCount++;
        }

        $table = Html::el('table')->addHtml(
            Html::el('thead')->addHtml($this->header()).
            Html::el('tbody')->addHtml($this->body())
        );

        $table->id = 'pdgrid_'.$this->_id;
        $table->data('multisort', $this->options['multisort']);
        $table->setClass('table table-condensed table-bordered table-striped pdgrid');
        $table->data('url', $this->options['url']);
        if ($this->options['ajax']) $table->{'data-ajax'} = $this->options['ajax'];
        $table->data('current-url', $this->url());
        if ($this->options['ajax']) {
            $table->data('current-url-ajax', $this->url([], true));
            $table->data('default-sort', $this->_defaultSort());
        }
        $table->data('delete-url', $this->options['delete']);
        if ($this->options['ajax-delete']) {
            $table->data('delete-ajax-url', $this->options['ajax-delete']);
        }

        if ($this->options['group'] && isset($this->options['group']['delete'])) {
            $table->data('deletegroup-url', $this->options['group']['delete']);
        }

        if ($this->options['group'] && isset($this->options['group']['ajax-delete'])) {
            $table->data('deletegroup-ajax-url', $this->options['group']['ajax-delete']);
        }

        if ($this->options['ajaxSetUrl']) {
            $table->data('ajax-set-url', 'true');
        }

        if ($this->options['footer']) {
            $table->addHtml(Html::el('tfoot')->addHtml(
                Html::el('tr')->addHtml(Html::el('td',
                        'Отображены записи с '. $this->_pg->first.
                        ' по '. $this->_pg->last .
                        '. Всего записей: '. $this->_pg->count
                    )->colspan($colCount)
                )
            ));
        }

        return Html::el('form')->addHtml($table)
            ->id('pdgrid_'.$this->_id.'_filter_form')
             ->action($this->url())->method('POST').
            $this->renderPaging().$this->assets->js();
    }

    public function ajax() {
        $result = [
            'head' => $this->header(),
            'body' => $this->body(),
            'total' => $this->_pg->count,
            'first' => $this->_pg->first,
            'last' => $this->_pg->last,
            'page' => $this->_pg->page,
            'paging' => $this->renderPaging(),
            'script' => $this->assets->js()
        ];
        echo json_encode($result);
        exit;
    }

    public function export($type, $title = '', $filename = 'export', $full = true) {
        $export = Export::create($type, $this);
        if ($full) {
            if ($this->_data !== false) {
                $rows = $this->_data;
            } else {
                $rows = $this->_db->fetchAll($this->_sql);
            }
        } else {
            $rows = $this->_pg->fetchPage();
        }
        $export->export($filename, $title, $rows);
        exit;
    }

    protected function _renderPageLink($page, $url, $label = null, $onclick = null) {
        $li = Html::el('li');
        $class = '';
        if ($this->_pg->page == $page) $class = 'active';
        if ($page < 1) {
            $class = 'disabled';
            $page = 1;
        }
        if ($page > $this->_pg->pageCount) {
            $class = 'disabled';
            $page = $this->_pg->pageCount;
        }
        if ($class !== '') $li->setClass($class);

        if ($this->_pg->page != $page) {
            $a = Html::el('a')
                    ->addHtml($label ?: $page)
                    ->href(str_replace(urlencode('{page}'), $page, $url))
                    ->onclick($onclick ?: "return $.pdgrid.gotoPage('{$this->_id}', '{$page}')");
        } else {
            $a = Html::el('a')->addHtml($label ?: $page)->href('#');
        }

        return strval($li->addHtml($a));
    }

    public function renderPaging()
    {
        if ($this->_pg->countPerPage == 0) return '';
        
        $url = $this->url(['page' => '{page}']);
        $count_first = 3;
        $count_prev = 4;
        $count_next = 5;
        $count_last = 1;

        $ul = Html::el('ul', ['class' => 'pagination']);

        if ($this->_pg->pageCount > 1) {

            $page = $this->_pg->page;
            $ul[] = $this->_renderPageLink($page - 1, $url, '&laquo; Назад');

            $start = max(1, min($page - $count_prev, $this->_pg->pageCount - $count_next - $count_prev));

            if ($start <= 5) $start = 1;
            if ($start > 1) {
                for ($i = 1; $i <= $count_first; $i++) {
                    $ul[] = $this->_renderPageLink($i, $url);
                }
                $ul[] = $this->_renderPageLink($count_first + 1, $url, '..', 'return $.pdgrid.selectPage("'.$this->_id.'", this, '.($count_first + 1).', '.$this->_pg->pageCount.')');
            }
            $end = min($this->_pg->pageCount, max($page + $count_next, $count_first + $count_prev + $count_next + 1));
            if ($end >= $this->_pg->pageCount - $count_last - 1) $end = $this->_pg->pageCount;
            for ($i = $start; $i <= $end; $i++) {
                $ul[] = $this->_renderPageLink($i, $url);
            }
            if ($end < $this->_pg->pageCount) {
                $ul[] = $this->_renderPageLink($end + 1, $url, '..', 'return $.pdgrid.selectPage("'.$this->_id.'", this, '.($end + 1).','.$this->_pg->pageCount.')');
                for ($i = $this->_pg->pageCount - $count_last + 1; $i <= $this->_pg->pageCount; $i++) {
                    $ul[] = $this->_renderPageLink($i, $url);
                }
            }

            $ul[] = $this->_renderPageLink($page + 1, $url, 'Вперед &raquo;');
        }

        return strval(Html::el('div', ['class' => "text-center"])->addHtml($ul)
            ->id('pdgrid_'.$this->_id.'_paging'));
    }

    protected function unparse_url($parsed_url) {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = (isset($parsed_url['fragment']) && $parsed_url['fragment'] !== '') ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public function url($params = [], $isAjax = false)
    {
        if ($isAjax && $this->options['ajax']) {
            $url = $this->options['ajax'];
        } else {
            $url = $this->options['url'];
        }

        $query = [];
        $urlParts = parse_url($url);
        if ($urlParts) {
            parse_str(isset($urlParts['query']) ? $urlParts['query'] : '', $query);
            $urlParts['query'] = '';
            $urlParts['fragment'] = '';
            $url = $this->unparse_url($urlParts);
        } else {
            $p = strpos($url, '?');
            if ($p !== FALSE) {
                parse_str(substr($url, $p + 1), $query);
                $url = substr($url, 0, $p);
            }
        }

        $currentParams = [];

        if ($this->_pg->page >= 2) $currentParams['page'] = $this->_pg->page;
        if (isset($_REQUEST['sort'])) $currentParams['sort'] = $_REQUEST['sort'];
        $filterParam = $this->getFilterParam();
        if ($filterParam != '') $currentParams['filter'] = $filterParam;
        //if (isset($_REQUEST['filter']) && $_REQUEST['filter'] != '') $currentParams['filter'] = $_REQUEST['filter'];


        if (!empty($currentParams)) {
            foreach ($currentParams as $name => $value) {
                if (!isset($value)) unset($query[$name]);
                else $query[$name] = $value;
            }
        }
        if (!empty($params)) {
            foreach($params as $name => $value)
            {
                if (!isset($value)) unset($query[$name]);
                else $query[$name] = $value;
            }
        }
        return $url . '?' . http_build_query($query);
    }

    public function appendSortAndFilterParams($url) {
       return $url.(isset($_REQUEST['sort'])? '&sort='. $_REQUEST['sort']:'').
       (isset($_REQUEST['filter'])? '&filter='. $_REQUEST['filter']:'');
    }


    public function getAddBtn() {
        if ($this->options['add']) {
            $addBtn = TwbsBtn::a_success(Glyphicon::plus.'  Добавить',  'pdgrid-'.$this->_id.'-addbtn');
            $addBtn->href($this->replace($this->options['add']));
            $addBtn->data('addurl', $this->options['add']);
            return strval($addBtn);
        }
        return '';
    }

    public function getExportBtn() {
        if ($this->options['export'] !== false) {

            $btn = TwbsBtn::a_def(
                Glyphicon::share.'  Экспорт'
            );

            if (is_array($this->options['export'])) {
                $ul = Html::el('ul', ['class' => 'dropdown-menu']);
                if ($this->options['export_menu_right']) {
                    $ul->appendAttribute('class', 'dropdown-menu-right');
                }
                foreach ($this->options['export'] as $title => $url) {
                    $a = Html::el('a',$title)->href(
                        $this->appendSortAndFilterParams($this->replace(
                            $url, ['page' => $this->_pg->page])))
                        ->setClass('pdgrid-'.$this->_id.'-export');
                    $a->data('url', $url);
                    $ul[] = Html::el('li')->addHtml($a);
                }
                $btn->addClass('dropdown-toggle');
                $btn->data('toggle', "dropdown");
                $btn->addHtml(' '.Html::el('span', ['class' => 'caret']));
                $div = Html::el('div', ['class' => 'btn-group'])->addHtml($btn. $ul);
                return strval($div);
            } else {
                $btn->href($this->replace($this->options['export']))
                    ->data('url', $this->options['export'])
                    ->addClass('pdgrid-'.$this->_id.'-export');
                return strval($btn);
            }
        }
        return '';
    }

    protected function replace($text, $values = null) {
        if (isset($values)) {
            foreach ($values as $id => $v) {
                $text = str_replace('{' . $id . '}', is_array($v) ? 'array' : $v, $text);
            }
        }
        return str_replace(['<script>','</script>'], '',
            str_replace('{returnUrl}', urlencode($this->url()), $text));
    }

    static $dateMonthNames = [
        'января','февраля','марта','апреля','мая','июня',
        'июля','августа','сентября','октября','ноября','декабря'
    ];

    static function formatDate($date = null)
    {
        if (!isset($date)) $date = time();
        if (!is_numeric($date)) $date = strtotime($date);
        $dt = getdate($date);
        $m = self::$dateMonthNames[$dt['mon']-1];
        return $dt['mday'] . ' ' . $m . ' ' . $dt['year'] . ' г.';
    }

    static function formatDateTime($date, $timeShort = true)
    {
        if (!is_numeric($date)) $date = strtotime($date);
        return self::formatDate($date) .' '. date($timeShort ? 'H:i': 'H:i:s', $date);
    }
}