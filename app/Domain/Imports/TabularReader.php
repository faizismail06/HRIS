<?php

namespace App\Domain\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

class TabularReader
{
    public function read(UploadedFile $file): array
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:10240']])->validate();
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'xlsx'], true), 422, 'Hanya CSV UTF-8 atau XLSX tanpa formula/macro.');
        try {
            $rows = $extension === 'csv' ? $this->csv($file->getRealPath()) : $this->xlsx($file->getRealPath());
        } catch (Exception $error) {
            abort(422, 'XLSX tidak dapat dibaca; gunakan template baru tanpa formula, macro atau external link.');
        }
        abort_unless(count($rows) >= 2 && count($rows) <= 5001, 422, 'Satu header dan1–5000baris data diperlukan.');
        $headers = array_map(fn ($v) => trim((string) $v), array_shift($rows));
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        abort_unless(count($headers) <= 50 && count(array_unique($headers)) === count($headers) && ! in_array('', $headers, true), 422, 'Header harus unik, terisi, maksimal50kolom.');
        foreach ($headers as $header) {
            abort_if(mb_strlen($header) > 100, 422);
        }
        foreach ($rows as &$row) {
            abort_if(count($row) > count($headers), 422, 'Jumlah kolom melebihi header.');
            $row = array_pad($row, count($headers), '');
            foreach ($row as &$value) {
                $value = trim((string) $value);
                abort_unless(! str_contains($value, "\0") && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= 2000, 422, 'Sel harus UTF-8, maksimal2000karakter dan tanpa null byte.');
                abort_if(str_starts_with($value, '='), 422, 'Formula tidak diizinkan.');
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function csv(string $path): array
    {
        $stream = fopen($path, 'rb');
        $rows = [];
        try {
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $rows[] = $row;
                abort_if(count($rows) > 5001 || count($row) > 50, 422, 'Batas5000baris/50kolom terlampaui.');
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'XLSX tidak valid.');
        try {
            abort_if($zip->numFiles > 2000, 422, 'Arsip terlalu kompleks.');
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = str_replace('\\', '/', $entry['name']);
                abort_if(str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, ':') || preg_match('/vbaProject|externalLinks|embeddings\//i', $name), 422, 'Macro, external link atau embedded object tidak diizinkan.');
                $total += $entry['size'];
                abort_if($total > 52428800 || ($entry['size'] > 1048576 && $entry['size'] > max(1, $entry['comp_size']) * 100), 422, 'Ukuran ekspansi arsip melampaui batas.');
            }
        } finally {
            $zip->close();
        }
        $reader = new Xlsx;
        $info = $reader->listWorksheetInfo($path);
        abort_unless(count($info) === 1 && $info[0]['totalRows'] <= 5001 && $info[0]['totalColumns'] <= 50, 422, 'XLSX harus satu sheet, maksimal5000baris/50kolom data.');
        $reader->setReadDataOnly(false)->setReadEmptyCells(false)->setIncludeCharts(false);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 5001 && Coordinate::columnIndexFromString($columnAddress) <= 50;
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getActiveSheet();
            $rows = [];
            for ($i = 1; $i <= $info[0]['totalRows']; $i++) {
                $values = [];
                for ($j = 1; $j <= $info[0]['totalColumns']; $j++) {
                    $cell = $sheet->getCell([$j, $i]);
                    abort_if($cell->getDataType() === DataType::TYPE_FORMULA, 422, 'Formula tidak diizinkan.');
                    $values[] = (string) ($cell->getValue() ?? '');
                }
                $rows[] = $values;
            }

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
