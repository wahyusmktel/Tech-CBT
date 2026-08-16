<?php

namespace App\Services;

use App\Exceptions\InvalidQuestionDocumentException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use Throwable;
use ZipArchive;

class QuestionDocxParser
{
    public function parse(UploadedFile $file): array
    {
        try {
            $this->assertSafeArchive($file->getRealPath());
            $document = IOFactory::load($file->getRealPath(), 'Word2007');
            $lines = [];
            foreach ($document->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text = trim($this->elementText($element));
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }

            return $this->parseLines($lines);
        } catch (Throwable $exception) {
            if (! $exception instanceof InvalidQuestionDocumentException) {
                Log::error('Parsing question DOCX failed.', ['exception' => $exception]);
            }
            throw $exception;
        }
    }

    private function parseLines(array $lines): array
    {
        $questions = [];
        $current = null;
        $lastChoice = null;
        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^(\d+)\.\s*(.+)$/u', $line, $matches)) {
                if ($current) {
                    $questions[] = $this->finish($current, $lineNumber);
                }
                $current = ['number' => (int) $matches[1], 'text' => trim($matches[2]), 'choices' => [], 'answer' => null];
                $lastChoice = null;

                continue;
            }
            if (! $current) {
                continue;
            }
            if (preg_match('/^([A-E])\.\s*(.+)$/iu', $line, $matches)) {
                $lastChoice = strtoupper($matches[1]);
                $current['choices'][$lastChoice] = trim($matches[2]);

                continue;
            }
            if (preg_match('/^ANS\s*:\s*([A-E])\s*$/iu', $line, $matches)) {
                $current['answer'] = strtoupper($matches[1]);
                $lastChoice = null;

                continue;
            }
            if ($lastChoice) {
                $current['choices'][$lastChoice] .= ' '.$line;
            } else {
                $current['text'] .= ' '.$line;
            }
        }
        if ($current) {
            $questions[] = $this->finish($current, count($lines));
        }
        if ($questions === []) {
            throw new InvalidQuestionDocumentException('Tidak ada soal dengan format yang valid pada dokumen.');
        }

        return $questions;
    }

    private function finish(array $question, int $line): array
    {
        if (count($question['choices']) < 2) {
            throw new InvalidQuestionDocumentException("Soal nomor {$question['number']} belum memiliki minimal dua pilihan.");
        }
        if (! $question['answer'] || ! isset($question['choices'][$question['answer']])) {
            throw new InvalidQuestionDocumentException("Kunci jawaban soal nomor {$question['number']} tidak valid (sekitar baris {$line}).");
        }

        return $question;
    }

    private function elementText(object $element): string
    {
        if (method_exists($element, 'getElements')) {
            return collect($element->getElements())->map(fn ($child) => $this->elementText($child))->implode('');
        }
        if (method_exists($element, 'getTextObject')) {
            return $this->elementText($element->getTextObject());
        }
        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_string($text) ? $text : '';
        }

        return '';
    }

    private function assertSafeArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidQuestionDocumentException('Dokumen DOCX tidak dapat dibaca.');
        }
        $total = 0;
        if ($zip->numFiles > 1000) {
            $zip->close();
            throw new InvalidQuestionDocumentException('Dokumen DOCX memiliki terlalu banyak bagian.');
        }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $total += (int) ($zip->statIndex($index)['size'] ?? 0);
            if ($total > 25 * 1024 * 1024) {
                $zip->close();
                throw new InvalidQuestionDocumentException('Ukuran isi dokumen DOCX terlalu besar.');
            }
        }
        $zip->close();
    }
}
