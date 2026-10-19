<?php

namespace App\Modules\Reports\Services;

use Illuminate\Database\Query\Builder;
use RuntimeException;
use ZipArchive;

class XlsxReportExporter
{
    /**
     * @param  array<int, array{field: string, label: string, type?: string}>  $columns
     */
    public function create(Builder $query, array $columns): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for Excel exports.');
        }

        $workbookPath = tempnam(sys_get_temp_dir(), 'nilambara-report-');
        if ($workbookPath === false) {
            throw new RuntimeException('Unable to allocate temporary files for the Excel export.');
        }
        $sheetPath = tempnam(sys_get_temp_dir(), 'nilambara-sheet-');
        if ($sheetPath === false) {
            @unlink($workbookPath);
            throw new RuntimeException('Unable to allocate temporary files for the Excel export.');
        }

        $sheet = fopen($sheetPath, 'wb');
        if ($sheet === false) {
            @unlink($workbookPath);
            @unlink($sheetPath);
            throw new RuntimeException('Unable to create the Excel worksheet.');
        }

        try {
            $this->write($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            $this->write($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
            $this->writeRow($sheet, 1, array_column($columns, 'label'));

            $rowNumber = 2;
            foreach ($query->limit(50000)->cursor() as $record) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = data_get($record, $column['field'], '');
                }
                $this->writeRow($sheet, $rowNumber++, $values);
            }

            $this->write($sheet, '</sheetData></worksheet>');
        } catch (\Throwable $exception) {
            @unlink($workbookPath);
            @unlink($sheetPath);
            throw $exception;
        } finally {
            fclose($sheet);
        }

        $zip = new ZipArchive;
        $opened = $zip->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            @unlink($workbookPath);
            @unlink($sheetPath);
            throw new RuntimeException('Unable to create the Excel workbook archive.');
        }

        $contents = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
        ];
        foreach ($contents as $name => $content) {
            if (! $zip->addFromString($name, $content)) {
                $zip->close();
                @unlink($workbookPath);
                @unlink($sheetPath);
                throw new RuntimeException('Unable to write the Excel workbook structure.');
            }
        }
        if (! $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml')) {
            $zip->close();
            @unlink($workbookPath);
            @unlink($sheetPath);
            throw new RuntimeException('Unable to add the Excel worksheet to the workbook.');
        }
        $closed = $zip->close();
        @unlink($sheetPath);

        if (! $closed) {
            @unlink($workbookPath);
            throw new RuntimeException('Unable to finalize the Excel workbook.');
        }

        return $workbookPath;
    }

    /**
     * @param  resource  $stream
     * @param  array<int, mixed>  $values
     */
    private function writeRow($stream, int $rowNumber, array $values): void
    {
        $this->write($stream, '<row r="'.$rowNumber.'">');
        foreach ($values as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $this->write($stream, '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>');
        }
        $this->write($stream, '</row>');
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /**
     * @param  resource  $stream
     */
    private function write($stream, string $content): void
    {
        if (fwrite($stream, $content) === false) {
            throw new RuntimeException('Unable to write the Excel export.');
        }
    }
}
