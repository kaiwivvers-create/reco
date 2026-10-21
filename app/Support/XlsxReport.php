<?php

namespace App\Support;

use ZipArchive;

/**
 * Minimal XLSX writer (multi-sheet, styled header, numeric cells) built on
 * ZipArchive so the app does not need an extra spreadsheet package.
 */
class XlsxReport
{
    /** @var array<int,array{name:string,rows:array,header:bool,widths:array}> */
    protected array $sheets = [];

    public function addSheet(string $name, array $rows, bool $headerRow = true, array $widths = []): static
    {
        $name = mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $name) ?: 'Sheet', 0, 31);
        $this->sheets[] = [
            'name' => $name,
            'rows' => $rows,
            'header' => $headerRow,
            'widths' => $widths,
        ];

        return $this;
    }

    public function output(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                $this->worksheet($sheet['rows'], $sheet['header'], $sheet['widths'])
            );
        }

        $zip->close();

        $binary = file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    protected function contentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $index => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1)
                .'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    protected function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($index + 1)
                .'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    protected function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $sheet) {
            $rels .= '<Relationship Id="rId'.($index + 1)
                .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                .'Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1)
            .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels.'</Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    protected function worksheet(array $rows, bool $header, array $widths): string
    {
        $columns = '';
        if ($widths) {
            $columns = '<cols>';
            foreach (array_values($widths) as $index => $width) {
                $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
            }
            $columns .= '</cols>';
        }

        $sheetData = '<sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $sheetData .= '<row r="'.($rowIndex + 1).'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $reference = $this->columnLetter($columnIndex + 1).($rowIndex + 1);
                $isHeader = $header && $rowIndex === 0;
                $style = $isHeader ? ' s="1"' : '';

                if ($value === null || $value === '') {
                    $sheetData .= '<c r="'.$reference.'"'.$style.'/>';
                } elseif (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && ! preg_match('/^0\d/', $value))) {
                    $sheetData .= '<c r="'.$reference.'"'.$style.'><v>'.$this->number($value).'</v></c>';
                } else {
                    $sheetData .= '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
                        .$this->escape((string) $value).'</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$columns.$sheetData.'</worksheet>';
    }

    protected function columnLetter(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $mod = ($number - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $number = (int) (($number - $mod) / 26);
        }

        return $letters;
    }

    protected function number(mixed $value): string
    {
        $value = (float) $value;

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }

    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
