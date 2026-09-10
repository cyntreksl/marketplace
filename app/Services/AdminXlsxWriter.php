<?php

namespace App\Services;

use DateTimeInterface;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class AdminXlsxWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<bool|DateTimeInterface|float|int|string|null>>  $rows
     */
    public function createTemporaryFile(string $sheetName, array $headers, iterable $rows): string
    {
        $workbookPath = tempnam(sys_get_temp_dir(), 'admin-export-');
        $sheetDataPath = tempnam(sys_get_temp_dir(), 'admin-export-data-');
        $worksheetPath = tempnam(sys_get_temp_dir(), 'admin-export-sheet-');

        if ($workbookPath === false || $sheetDataPath === false || $worksheetPath === false) {
            throw new RuntimeException('Unable to create the Excel export.');
        }

        try {
            $lastRow = $this->writeSheetData($sheetDataPath, $headers, $rows);
            $this->writeWorksheet($worksheetPath, $sheetDataPath, count($headers), $lastRow);
            $this->writeWorkbook($workbookPath, $worksheetPath, $sheetName);
        } catch (\Throwable $exception) {
            @unlink($workbookPath);

            throw $exception;
        } finally {
            @unlink($sheetDataPath);
            @unlink($worksheetPath);
        }

        return $workbookPath;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<bool|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function writeSheetData(string $path, array $headers, iterable $rows): int
    {
        $writer = new XMLWriter;
        if (! $writer->openUri($path)) {
            throw new RuntimeException('Unable to prepare the Excel worksheet data.');
        }

        $writer->startElement('sheetData');
        $this->writeRow($writer, 1, $headers, true);
        $rowNumber = 2;

        foreach ($rows as $row) {
            $this->writeRow($writer, $rowNumber++, $row);
        }

        $writer->endElement();
        $writer->flush();

        return $rowNumber - 1;
    }

    /** @param list<bool|DateTimeInterface|float|int|string|null> $values */
    private function writeRow(XMLWriter $writer, int $rowNumber, array $values, bool $header = false): void
    {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $rowNumber);

        foreach ($values as $index => $value) {
            if ($value === null) {
                continue;
            }

            $writer->startElement('c');
            $writer->writeAttribute('r', $this->columnName($index + 1).$rowNumber);

            if ($header) {
                if (! is_string($value)) {
                    throw new RuntimeException('Excel export headers must be text.');
                }
                $writer->writeAttribute('s', '1');
                $writer->writeAttribute('t', 'inlineStr');
                $writer->startElement('is');
                $writer->startElement('t');
                $writer->writeAttribute('xml:space', 'preserve');
                $writer->text($value);
                $writer->endElement();
                $writer->endElement();
            } elseif (is_string($value)) {
                $writer->writeAttribute('t', 'inlineStr');
                $writer->startElement('is');
                $writer->startElement('t');
                $writer->writeAttribute('xml:space', 'preserve');
                $writer->text((string) $value);
                $writer->endElement();
                $writer->endElement();
            } elseif ($value instanceof DateTimeInterface) {
                $writer->writeAttribute('s', '2');
                $writer->writeElement('v', $this->excelDateValue($value));
            } elseif (is_bool($value)) {
                $writer->writeAttribute('t', 'b');
                $writer->writeElement('v', $value ? '1' : '0');
            } else {
                $writer->writeElement('v', (string) $value);
            }

            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeWorksheet(string $path, string $sheetDataPath, int $columnCount, int $lastRow): void
    {
        $worksheet = fopen($path, 'wb');
        $sheetData = fopen($sheetDataPath, 'rb');

        if ($worksheet === false || $sheetData === false) {
            if (is_resource($worksheet)) {
                fclose($worksheet);
            }
            if (is_resource($sheetData)) {
                fclose($sheetData);
            }

            throw new RuntimeException('Unable to assemble the Excel worksheet.');
        }

        try {
            $lastColumn = $this->columnName($columnCount);
            fwrite($worksheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            fwrite($worksheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
            fwrite($worksheet, '<dimension ref="A1:'.$lastColumn.$lastRow.'"/>');
            fwrite($worksheet, '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
            fwrite($worksheet, '<sheetFormatPr defaultRowHeight="15"/><cols><col min="1" max="'.$columnCount.'" width="20" customWidth="1"/></cols>');
            stream_copy_to_stream($sheetData, $worksheet);
            fwrite($worksheet, '<autoFilter ref="A1:'.$lastColumn.$lastRow.'"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>');
        } finally {
            fclose($sheetData);
            fclose($worksheet);
        }
    }

    private function writeWorkbook(string $workbookPath, string $worksheetPath, string $sheetName): void
    {
        $zip = new ZipArchive;
        if ($zip->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel workbook.');
        }

        foreach ($this->workbookParts($sheetName) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->addFile($worksheetPath, 'xl/worksheets/sheet1.xml');

        if (! $zip->close()) {
            throw new RuntimeException('Unable to finalize the Excel workbook.');
        }
    }

    /** @return array<string, string> */
    private function workbookParts(string $sheetName): array
    {
        $createdAt = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $safeSheetName = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ProDeals.lk</Application></Properties>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>ProDeals.lk</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$createdAt.'</dcterms:created></cp:coreProperties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="'.$safeSheetName.'" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="999999" calcMode="auto" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts><fonts count="2"><font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1461CC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf xfId="0" numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf xfId="0" numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf xfId="0" numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        ];
    }

    private function excelDateValue(DateTimeInterface $value): string
    {
        return rtrim(rtrim(number_format(($value->getTimestamp() / 86400) + 25569, 10, '.', ''), '0'), '.');
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}
