<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class ExamReportExportService
{
    public function createExcel(array $report, School $school): string
    {
        $path = null;

        try {
            $spreadsheet = new Spreadsheet;
            $this->fillResultSheet($spreadsheet, $report, $school);
            $this->fillAnalysisSheet($spreadsheet, $report, $school);

            $directory = storage_path('app/private/reports');
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException('Report directory could not be created.');
            }

            $path = tempnam($directory, 'exam-report-');
            if ($path === false) {
                throw new RuntimeException('Temporary report file could not be created.');
            }

            (new Xlsx($spreadsheet))->save($path);
            $spreadsheet->disconnectWorksheets();

            return $path;
        } catch (Throwable $exception) {
            if ($path && is_file($path)) {
                unlink($path);
            }
            Log::error('Creating exam Excel report failed.', ['exception' => $exception]);

            throw $exception;
        }
    }

    public function letterheadDataUri(School $school): ?string
    {
        try {
            if (! $school->letterhead_path || ! Storage::disk('public')->exists($school->letterhead_path)) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($school->letterhead_path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($school->letterhead_path));
        } catch (Throwable $exception) {
            Log::warning('Reading school letterhead for report failed.', ['school_id' => $school->id, 'exception' => $exception]);

            return null;
        }
    }

    private function fillResultSheet(Spreadsheet $spreadsheet, array $report, School $school): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Nilai');
        $sheet->setShowGridlines(false);
        $this->writeHeading($sheet, $school, $report, 'REKAP HASIL UJIAN');
        $sheet->mergeCells('A7:G7')->setCellValue('A7', 'Peserta: '.$report['summary']['participant_count'].' | Selesai: '.$report['summary']['finished_count'].' | Rata-rata: '.($report['summary']['average_score'] ?? '-').' | Tertinggi: '.($report['summary']['highest_score'] ?? '-').' | Terendah: '.($report['summary']['lowest_score'] ?? '-'));
        $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['No', 'NISN', 'Nama Siswa', 'Kelas', 'Status', 'Nilai', 'Waktu Selesai'], null, 'A8');

        foreach ($report['results'] as $index => $result) {
            $sheet->fromArray([
                $index + 1,
                null,
                $result['name'],
                $result['classroom'],
                $this->statusLabel($result['status']),
                $result['score'],
                $result['finished_at'] ? date('d/m/Y H:i', strtotime($result['finished_at'])) : '-',
            ], null, 'A'.($index + 9));
            $sheet->setCellValueExplicit('B'.($index + 9), $result['nisn'], DataType::TYPE_STRING);
        }

        $lastRow = max(9, count($report['results']) + 8);
        $this->styleTable($sheet, 'A8:G'.$lastRow);
        $sheet->getStyle('F9:F'.$lastRow)->getNumberFormat()->setFormatCode('0.00');
        $sheet->setAutoFilter('A8:G'.$lastRow);
        $sheet->freezePane('A9');
        $this->setWidths($sheet, ['A' => 7, 'B' => 18, 'C' => 34, 'D' => 16, 'E' => 18, 'F' => 12, 'G' => 22]);
    }

    private function fillAnalysisSheet(Spreadsheet $spreadsheet, array $report, School $school): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Analisis Soal');
        $sheet->setShowGridlines(false);
        $this->writeHeading($sheet, $school, $report, 'ANALISIS BUTIR SOAL');
        $sheet->mergeCells('A7:G7')->setCellValue('A7', 'Peserta selesai: '.$report['summary']['finished_count'].' | Jumlah soal: '.$report['exam']['question_count']);
        $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->fromArray(['No', 'Pertanyaan', 'Kunci', 'Benar', 'Salah', 'Kosong', '% Benar'], null, 'A8');

        foreach ($report['question_analysis'] as $index => $item) {
            $sheet->fromArray([$item['number'], $item['text'], $item['correct_answer'], $item['correct_count'], $item['wrong_count'], $item['unanswered_count'], $item['correct_percentage'] / 100], null, 'A'.($index + 9));
        }

        $lastRow = max(9, count($report['question_analysis']) + 8);
        $this->styleTable($sheet, 'A8:G'.$lastRow);
        $sheet->getStyle('G9:G'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('B9:B'.$lastRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('A9');
        $this->setWidths($sheet, ['A' => 7, 'B' => 70, 'C' => 10, 'D' => 11, 'E' => 11, 'F' => 11, 'G' => 13]);
    }

    private function writeHeading($sheet, School $school, array $report, string $title): void
    {
        $sheet->mergeCells('A1:G1')->setCellValue('A1', strtoupper($school->name));
        $sheet->mergeCells('A2:G2')->setCellValue('A2', $school->address);
        $sheet->mergeCells('A4:G4')->setCellValue('A4', $title);
        $sheet->mergeCells('A5:G5')->setCellValue('A5', $report['exam']['name'].' - '.$report['exam']['subject']);
        $sheet->mergeCells('A6:G6')->setCellValue('A6', 'Tanggal ujian: '.date('d/m/Y H:i', strtotime($report['exam']['start_at'])));
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A4:G4')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1:G6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->addLetterhead($sheet, $school);
    }

    private function addLetterhead($sheet, School $school): void
    {
        if (! $school->letterhead_path || ! Storage::disk('public')->exists($school->letterhead_path)) {
            return;
        }

        $sheet->setCellValue('A1', '')->setCellValue('A2', '');
        $sheet->getRowDimension(1)->setRowHeight(38);
        $sheet->getRowDimension(2)->setRowHeight(38);
        $drawing = new Drawing;
        $drawing->setName('Kop Surat '.$school->name);
        $drawing->setPath(Storage::disk('public')->path($school->letterhead_path));
        $drawing->setCoordinates('B1');
        $drawing->setHeight(92);
        $drawing->setOffsetX(14);
        $drawing->setWorksheet($sheet);
    }

    private function styleTable($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
        $header = preg_replace('/\d+:[A-Z]+\d+$/', '8:G8', $range);
        $sheet->getStyle($header)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDC2626');
        $sheet->getStyle($header)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function setWidths($sheet, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finished' => 'Selesai',
            'in_progress' => 'Mengerjakan',
            default => 'Belum mulai',
        };
    }
}
