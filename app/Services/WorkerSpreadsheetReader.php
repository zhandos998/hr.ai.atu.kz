<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

class WorkerSpreadsheetReader
{
    public const MAX_ROWS = 200;

    public function read(string $path): array
    {
        try {
            return $this->readWorkbook($path);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $exception) {
            $this->fail('Не удалось прочитать Excel-файл. Проверьте формат и содержимое.');
        }
    }

    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Не удалось открыть Excel-файл.');
        }
        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += ($zip->statIndex($index)['size'] ?? 0);
            }
            if ($zip->numFiles > 512 || $size > 20 * 1024 * 1024) {
                $this->fail('Excel-файл слишком большой.');
            }
        } finally {
            $zip->close();
        }

        $reader = new Xlsx;
        $info = $reader->listWorksheetInfo($path);
        if (count($info) !== 1 || $info[0]['totalRows'] > self::MAX_ROWS + 1) {
            $this->fail('Ожидается один лист Excel, не более 200 сотрудников.');
        }
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly($info[0]['worksheetName']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return in_array($columnAddress, ['A', 'B', 'C', 'D', 'E'], true) && $row <= WorkerSpreadsheetReader::MAX_ROWS + 1;
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheet(0);
            $rows = [];
            for ($number = 1; $number <= $sheet->getHighestDataRow(); $number++) {
                $values = [];
                foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
                    $cell = $sheet->getCell($column.$number);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $this->fail('Формулы в импортируемых ячейках не допускаются.');
                    }
                    $values[] = (string) $cell->getFormattedValue();
                }
                if (implode('', $values) === '') {
                    continue;
                }
                if ($number === 1 && in_array(mb_strtolower(trim($values[0])), ['фио', 'ф.и.о.', 'name'], true) && in_array(mb_strtolower(trim($values[1])), ['логин', 'login', 'username'], true)) {
                    continue;
                }
                $name = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $values[0]))));
                $rows[] = ['row_number' => $number, 'name' => $name, 'username' => trim($values[1]), 'password' => $values[2], 'details' => trim($values[3])];
            }
            if ($rows === [] || count($rows) > self::MAX_ROWS) {
                $this->fail('В файле должно быть от 1 до 200 сотрудников.');
            }

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
