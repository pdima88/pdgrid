<?php

namespace pdgrid\Export;

use pdgrid\Export;

class Csv extends Export {

    static function quoteCsv($s) {
        return '"'.str_replace('"', '""', $s).'"';
    }

    function export($filename, $title, $data)
    {
        $csv = '';
        $c = 0;

        if ($this->_grid->options['rownum']) {
            $csv.=';';
        }
        foreach ($this->_grid->columns as $colId => &$col) {
            $columnTitle = isset($col['title']) ? $col['title'] : '';
            if ($c > 0) $csv .= ';';
            $csv .= self::quoteCsv($columnTitle);
            $c++;
        }
        unset($col);

        if ($this->_grid->options['rownum']) {
            $c++;
        }
        $csv = self::quoteCsv($title).str_pad('',$c-1,';').PHP_EOL.$csv.PHP_EOL;

        foreach ($data as $i => $row) {
            $first = true;
            if ($this->_grid->options['rownum']) {
                $csv .= $i+1;
                $first = false;
            }
            foreach ($this->_grid->columns as $colId => &$col) {
                $value = isset($row[$colId]) ? $row[$colId] : '';
                if (isset($col['format']) && is_array($col['format']) && isset($col['format'][$value])) {
                    $value = $col['format'][$value];
                }
                $value = strip_tags($value);

                if (is_numeric($value)) {
                    if (strlen($value) > 10) {
                        $cell = '=' . self::quoteCsv($value);
                    } else {
                        $cell = $value;
                    }
                    $cell = str_replace('.',',',$cell);
                } else {
                    if (isset($value) && $value !== '') {
                        $cell = self::quoteCsv($value);
                    } else {
                        $cell = '';
                    }
                }
                if (!$first) {
                    $csv .= ';';
                }
                $csv .= $cell;
                $first = false;
            }
            unset($col);
            $csv .= PHP_EOL;
        }

        header ( "Expires: " . gmdate("D,d M YH:i:s") . " GMT" );
        header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
        header ( "Cache-Control: no-cache, must-revalidate" );
        header ( "Pragma: no-cache" );

        header('Content-Type: text/csv');
        header ( "Content-Disposition: attachment; filename=$filename.csv" );

        $csv = str_replace("\n","\r\n", str_replace("\r\n", "\n", $csv));
        $r = iconv('UTF-8', 'Windows-1251//IGNORE', $csv);
        if ($r != FALSE) {
            echo $r;
        } else {
            echo $csv;
        }
    }
}