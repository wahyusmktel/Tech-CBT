<?php

namespace App\Services;

use App\Exceptions\CredentialsAlreadyGeneratedException;
use App\Exceptions\InvalidCredentialGenerationException;
use App\Models\Exam;
use App\Models\ExamStudentCredential;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ExamCredentialService
{
    public function generate(Exam $exam, array $options): int
    {
        try {
            if ($exam->credentials()->exists() && ! ($options['force'] ?? false)) {
                throw new CredentialsAlreadyGeneratedException('Kredensial ujian sudah pernah dibuat.');
            }
            $roomIds = $exam->roomAssignments()->pluck('room_id');
            $students = Student::query()->where('school_id', $exam->school_id)->whereHas('roomAssignments', fn ($query) => $query->whereIn('room_id', $roomIds))->get();
            if ($students->isEmpty()) {
                throw new InvalidCredentialGenerationException('Belum ada siswa yang dipetakan ke ruang ujian terpilih.');
            }

            $exam->credentials()->delete();
            foreach ($students as $student) {
                ExamStudentCredential::query()->create([
                    'school_id' => $exam->school_id,
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                    'username' => $options['username_strategy'] === 'nisn' ? $student->nisn : 's'.Str::lower(Str::random(10)),
                    'password' => $this->password($options['password_type'], $options['password_length']),
                ]);
            }
            $exam->update(['credentials_generated_at' => now()]);

            return $students->count();
        } catch (Throwable $exception) {
            if (! $exception instanceof CredentialsAlreadyGeneratedException && ! $exception instanceof InvalidCredentialGenerationException) {
                Log::error('Generating exam credentials failed.', ['exception' => $exception]);
            }
            throw $exception;
        }
    }

    private function password(string $type, int $length): string
    {
        $characters = match ($type) {
            'numeric' => '0123456789', 'letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz', default => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
        };

        return collect(range(1, $length))->map(fn () => $characters[random_int(0, strlen($characters) - 1)])->implode('');
    }
}
