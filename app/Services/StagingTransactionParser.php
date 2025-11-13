<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StagingTransactionParser
{
    public static function parse($filePath, $fileType, $source = null)
    {
        $records = [];
        if ($fileType === 'json') {
            $json = json_decode(file_get_contents($filePath), true);
            $rows = is_array($json) ? $json : [$json];
            foreach ($rows as $i => $row) {
                $records[] = self::makeStagingRow($row, $source, $i + 1);
            }
        } elseif ($fileType === 'csv') {
            $rows = array_map('str_getcsv', file($filePath));
            $header = array_map('trim', array_shift($rows));
            foreach ($rows as $i => $row) {
                $data = array_combine($header, $row);
                $records[] = self::makeStagingRow($data, $source, $i + 1);
            }
        } elseif ($fileType === 'xlsx') {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $header = [];
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $cells = [];
                foreach ($cellIterator as $cell) {
                    $cells[] = $cell->getValue();
                }
                if ($rowIndex === 1) {
                    $header = array_map('trim', $cells);
                } else {
                    $data = array_combine($header, $cells);
                    $records[] = self::makeStagingRow($data, $source, $rowIndex);
                }
            }
        } else {
            throw new \Exception('Unsupported file type');
        }
        return $records;
    }

    protected static function makeStagingRow($raw, $source, $id)
    {
        return [
            'id' => $id,
            'source' => $source ?? 'unknown',
            'raw' => $raw,
            'status' => 'pending',
            'mapping' => new \stdClass(),
            'ai_suggestions' => new \stdClass(),
            'notes' => '',
        ];
    }
}
