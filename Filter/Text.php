<?php

namespace pdgrid\Filter;

use pdgrid\Filter;
use pdima88\phpassets\Assets;
use Nette\Utils\Html;

class Text extends Filter
{
    protected $dataList = null;
    protected $dataListId = null;
    protected $placeholder = false;
    protected $tooltip = '';

    static $dataListIds = [];

    public function init(&$column)
    {
        if ($this->hasRequestParam()) {
            $this->_value = $this->getRequestParam();
        }

        Assets::addScript('
            function pdgrid_filter_text_help() {
                eModal.alert("Для поиска по шаблону используйте в тексте:<br>" +
                    "<b>знаки процента %</b> - любое количество (в.т.ч. и 0), любых символов<br>" +
                    "<b>подчеркивания _</b> - один любой символ. <br>" +
                    "Например, чтобы найти элементы начинающиеся с \"ав\" - используйте шаблон ав%, " +
                    "чтобы найти оканчивающиеся на \"ая\" - шаблон %ая.<br><br> " +
                    "По умолчанию (если знак % не используется в шаблоне) будут отобраны все элементы, " +
                    "содержащие заданный шаблон в любом месте строки.<br><br> " +
                    "Если вам нужно найти сам знак % или подчеркивания, экранируйте его с помощью " +
                    "обратной косой черты - \\\\%, \\\\_<br><br>" +
                    "Чтобы найти все пустые поля используйте шаблон _<br>чтобы найти все непустые поля - %",
                    "Фильтр по тексту: подсказка");
            }
        ', self::class .':js');

        if (isset($column['filterList'])) {
            $this->dataList = [];
            foreach ($column['filterList'] as $item) {
                if (is_array($item)) {
                    if (isset($item['title'])) $this->dataList[] = $item['title'];
                    elseif (isset($item['name'])) $this->dataList[] = $item['name'];
                } else {
                    $this->dataList[] = $item;
                }
            }

            $this->dataListId = 'filter_'.$this->_name.'_datalist'; $i = 1;
            while (isset(self::$dataListIds[$this->dataListId])) {
                $this->dataListId = 'filter_'.$this->_name.'_datalist'.(++$i);
            }
            self::$dataListIds[$this->dataListId] = true;
        };

        if (isset($column['filterPlaceholder'])) {
            $this->placeholder = $column['filterPlaceholder'];
        } elseif (isset($column['filter-placeholder'])) {
            $this->placeholder = $column['filter-placeholder'];
        }

        if (isset($column['filterTooltip'])) {
            $this->tooltip = $column['filterTooltip'];
        } elseif (isset($column['filter-tooltip'])) {
            $this->tooltip = $column['filter-tooltip'];
        }
    }

    function renderFilter()
    {
        $input = Html::el('input')->type('text')
            ->name('filter_'.$this->_name)
            ->setClass("form-control input-sm");
        $input->data('filter', $this->_name);
        if ($this->placeholder) {
            $input->placeholder($this->placeholder);
        }
        if ($this->tooltip) {
            $input->title($this->tooltip);
        }

        if ($this->dataList) {
            $input->list($this->dataListId);
            /** @var Html $dl */
            $dl = Html::el('datalist')->id($this->dataListId);
            foreach ($this->dataList as $s) {
                $dl->addHtml(
                    Html::el('option')->value($s)
                );
            }
        } else $dl = '';

        if ($this->isActive()) $input->value($this->_value);

        $clear = Html::el('span')->setClass('form-control-clear glyphicon glyphicon-remove')
            ->filterId($this->_name);
        if (!$this->isActive()) $clear->addClass('hidden');

        return Html::el('div')->addHtml(
            $input.$dl)->addHtml($clear)
            ->addHtml(
            Html::el('a')->setClass('form-control-feedback glyphicon glyphicon-question-sign')
                ->onclick('pdgrid_filter_text_help()')
        )->setClass('has-clear has-feedback feedback-hidden');

    }

    function isActive()
    {
        return isset($this->_value) && $this->_value !== '';
    }

    function getWhere()
    {
        if ($this->isActive()) {
            $db = \Zend_Db_Table::getDefaultAdapter();
            $v = $this->_value;
            if ($v == '_') return $db->quoteIdentifier($this->_name) . ' IS NULL OR '
                                 .$db->quoteIdentifier($this->_name).' = \'\'';
            $vc = str_replace('\\%', '', $v);
            if (strpos($vc, '%') === false) $v = '%'.$v.'%';
            return $db->quoteIdentifier($this->_name) . ' LIKE ' .
            $db->quote($v);
        }
        return '';
    }
}