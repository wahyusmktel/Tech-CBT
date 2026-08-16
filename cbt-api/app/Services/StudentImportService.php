<?php

namespace App\Services;

use App\Exceptions\InvalidStudentImportException;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

class StudentImportService
{
    public function import(UploadedFile $file, string $schoolId): array
    {
        try {
            $rows = $this->readRows($file);
            $headerIndex = collect(array_slice($rows, 0, 10))->search(function ($row): bool {
                $candidate = array_map(fn ($value) => $this->normalizeHeader($value), $row);

                return count(array_intersect(['nisn', 'nama', 'kelas'], $candidate)) === 3;
            });
            if ($headerIndex === false) {
                throw new InvalidStudentImportException('Header file wajib berisi kolom NISN, Nama, dan Kelas.');
            }
            $header = array_map(fn ($value) => $this->normalizeHeader($value), $rows[$headerIndex]);
            $columns = array_flip($header);
            $rows = array_slice($rows, $headerIndex + 1);
            $firstDataLine = $headerIndex + 2;

            DB::beginTransaction();
            $result = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
            $classrooms = [];

            foreach ($rows as $index => $row) {
                $line = $index + $firstDataLine;
                $nisn = trim((string) ($row[$columns['nisn']] ?? ''));
                $name = trim((string) ($row[$columns['nama']] ?? ''));
                $className = trim((string) ($row[$columns['kelas']] ?? ''));

                if ($nisn === '' && $name === '' && $className === '') {
                    continue;
                }
                if ($nisn === '' || $name === '' || $className === '' || strlen($nisn) > 30 || strlen($name) > 255 || strlen($className) > 100) {
                    $result['failed']++;
                    if (count($result['errors']) < 20) {
                        $result['errors'][] = "Baris {$line}: data tidak lengkap atau terlalu panjang.";
                    }

                    continue;
                }

                $classKey = Str::lower($className);
                if (! isset($classrooms[$classKey])) {
                    $classrooms[$classKey] = Classroom::query()->firstOrCreate(['school_id' => $schoolId, 'name' => $className]);
                }

                $student = Student::query()->firstOrNew(['school_id' => $schoolId, 'nisn' => $nisn]);
                $isNew = ! $student->exists;
                $student->fill(['classroom_id' => $classrooms[$classKey]->id, 'name' => $name])->save();
                $result[$isNew ? 'inserted' : 'updated']++;
            }

            DB::commit();

            return $result;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (! $exception instanceof InvalidStudentImportException) {
                Log::error('Student import failed.', ['exception' => $exception]);
            }
            throw $exception;
        }
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $reader = match ($extension) {
            'csv', 'txt' => new Csv,
            'xlsx' => new Xlsx,
            'xls' => new Xls,
            default => throw new InvalidStudentImportException('Format file tidak didukung.'),
        };

        if ($reader instanceof Csv) {
            $handle = fopen($file->getRealPath(), 'rb');
            if ($handle === false) {
                throw new InvalidStudentImportException('File CSV tidak dapat dibaca.');
            }
            $firstLine = (string) fgets($handle);
            fclose($handle);
            $reader->setDelimiter(substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',');
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        if ($sheet->getHighestDataRow() > 10000) {
            $spreadsheet->disconnectWorksheets();
            throw new InvalidStudentImportException('File dibatasi maksimal 10.000 baris per proses import.');
        }
        $rows = $sheet->toArray(null, false, true, false);
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function normalizeHeader(mixed $value): string
    {
        return Str::lower(trim(str_replace("\xEF\xBB\xBF", '', (string) $value)));
    }
}
