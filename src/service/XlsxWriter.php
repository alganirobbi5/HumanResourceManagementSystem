<?php

/**
 * Penulis file Excel (.xlsx) minimum tanpa library eksternal.
 * Mendukung beberapa worksheet, string inline, angka, dan nama sheet aman.
 */
class XlsxWriter
{
    private $sheets = array();

    public function addSheet($name, array $rows)
    {
        $this->sheets[] = array('name' => $this->safeSheetName($name, count($this->sheets)), 'rows' => $rows);
    }

    public function sheetCount()
    {
        return count($this->sheets);
    }

    /**
     * Simpan workbook ke path file. Mengembalikan path file.
     */
    public function save($filePath)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi PHP zip tidak tersedia untuk membuat file .xlsx');
        }
        if (empty($this->sheets)) {
            throw new RuntimeException('Tidak ada worksheet untuk ditulis');
        }

        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Gagal membuat folder tujuan file export');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file Excel');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->worksheetXml($sheet['rows']));
        }

        $zip->close();
        return $filePath;
    }

    private function safeSheetName($name, $index)
    {
        // Karakter yang dilarang di nama sheet Excel: \ / ? * [ ] :
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', (string)$name);
        $name = trim($name);
        if ($name === '') {
            $name = 'Sheet' . ($index + 1);
        }
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 31, 'UTF-8');
        } else {
            $name = substr($name, 0, 31);
        }

        // Pastikan unik
        $base = $name;
        $counter = 1;
        while ($this->sheetExists($name)) {
            $suffix = '-' . $counter++;
            $trim = 31 - strlen($suffix);
            $name = (function_exists('mb_substr') ? mb_substr($base, 0, $trim, 'UTF-8') : substr($base, 0, $trim)) . $suffix;
        }
        return $name;
    }

    private function sheetExists($name)
    {
        foreach ($this->sheets as $sheet) {
            if ($sheet['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    private function esc($value)
    {
        $value = (string)$value;
        $value = str_replace(array('&', '<', '>', '"', "'"), array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'), $value);
        // Buang karakter kontrol yang tidak valid di XML
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        return $value;
    }

    private function columnLetter($index)
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - $mod - 1, 26);
        }
        return $letters;
    }

    private function worksheetXml(array $rows)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $rowNum = 0;
        foreach ($rows as $row) {
            $rowNum++;
            $xml .= '<row r="' . $rowNum . '">';
            $colNum = 0;
            foreach ($row as $cell) {
                $ref = $this->columnLetter($colNum) . $rowNum;
                if (is_int($cell) || is_float($cell)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
                } elseif (is_numeric($cell) && is_string($cell) && !preg_match('/^0\d/', (string)$cell)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($cell) . '</t></is></c>';
                }
                $colNum++;
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function contentTypesXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '</Types>';
        return $xml;
    }

    private function relsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<sheet name="' . $this->esc($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function workbookRelsXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function stylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
