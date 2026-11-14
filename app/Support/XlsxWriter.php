<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Tiny .xlsx writer (one sheet, header row bold, numbers stored as numbers) so reports export to
 * Excel without a heavy library (D-08). Needs the PHP zip extension.
 */
final class XlsxWriter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function write(string $path, string $sheetName, array $headers, array $rows, ?string $title = null): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed for Excel export.');
        }

        $sheetName = mb_substr(preg_replace('/[\[\]\*\/\\\\\?:]/', ' ', $sheetName) ?? 'Report', 0, 31);
        $xmlRows = [];
        $r = 1;
        if ($title !== null) {
            $xmlRows[] = '<row r="1">'.self::cell('A1', $title, 1).'</row>';
            $r = 3;
        }
        $xmlRows[] = self::row($r++, $headers, 1);
        foreach ($rows as $row) {
            $xmlRows[] = self::row($r++, $row, 0);
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private static function row(int $r, array $values, int $style): string
    {
        $cells = '';
        foreach (array_values($values) as $i => $v) {
            $cells .= self::cell(self::column($i).$r, $v, $style);
        }

        return '<row r="'.$r.'">'.$cells.'</row>';
    }

    private static function cell(string $ref, string|int|float|null $v, int $style): string
    {
        $s = $style > 0 ? ' s="'.$style.'"' : '';
        if ($v === null || $v === '') {
            return '<c r="'.$ref.'"'.$s.'/>';
        }
        if (is_int($v) || is_float($v)) {
            return '<c r="'.$ref.'"'.$s.'><v>'.$v.'</v></c>';
        }

        return '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::esc($v).'</t></is></c>';
    }

    private static function column(int $i): string
    {
        $name = '';
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + (($n - 1) % 26)).$name;
        }

        return $name;
    }

    private static function esc(string $v): string
    {
        // Strip control characters that are invalid in XML; block formula injection when opened.
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? '';

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
