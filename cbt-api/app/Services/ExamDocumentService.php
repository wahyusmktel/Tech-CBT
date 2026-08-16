<?php

namespace App\Services;

use App\Exceptions\ExamCredentialsMissingException;
use App\Models\Exam;
use App\Models\School;
use App\Models\StudentRoomAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExamDocumentService
{
    public function attendance(Exam $exam): array
    {
        try {
            $context = $this->baseContext($exam);
            $context['rooms'] = $this->participantsByRoom($exam);

            return $context;
        } catch (Throwable $exception) {
            Log::error('Building exam attendance document failed.', ['exam_id' => $exam->id, 'exception' => $exception]);

            throw $exception;
        }
    }

    public function minutes(Exam $exam): array
    {
        try {
            $context = $this->baseContext($exam);
            $rooms = $this->participantsByRoom($exam);
            $attempts = $exam->attempts()->get()->keyBy('student_id');

            $context['rooms'] = $rooms->map(function (array $room) use ($attempts): array {
                $studentIds = collect($room['participants'])->pluck('id');
                $roomAttempts = $attempts->only($studentIds->all());
                $room['started_count'] = $roomAttempts->count();
                $room['finished_count'] = $roomAttempts->where('status', 'finished')->count();

                return $room;
            });

            return $context;
        } catch (Throwable $exception) {
            Log::error('Building exam minutes document failed.', ['exam_id' => $exam->id, 'exception' => $exception]);

            throw $exception;
        }
    }

    public function cards(Exam $exam): array
    {
        try {
            $exam->loadMissing(['credentials.student.classroom']);
            if ($exam->credentials->isEmpty()) {
                throw new ExamCredentialsMissingException;
            }

            $context = $this->baseContext($exam);
            $roomByStudent = StudentRoomAssignment::query()
                ->where('school_id', $exam->school_id)
                ->whereIn('room_id', $exam->roomAssignments->pluck('room_id'))
                ->with('room')
                ->get()
                ->keyBy('student_id');

            $context['cards'] = $exam->credentials
                ->sortBy(fn ($credential) => $credential->student->name)
                ->values()
                ->map(fn ($credential): array => [
                    'student_id' => $credential->student->id,
                    'nisn' => $credential->student->nisn,
                    'name' => $credential->student->name,
                    'classroom' => $credential->student->classroom?->name ?? '-',
                    'room' => $roomByStudent->get($credential->student_id)?->room?->name ?? '-',
                    'username' => $credential->username,
                    'password' => $credential->password,
                    'photo' => $this->imageDataUri($credential->student->photo_path),
                    'initials' => collect(explode(' ', $credential->student->name))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join(''),
                ]);

            return $context;
        } catch (Throwable $exception) {
            Log::error('Building exam card document failed.', ['exam_id' => $exam->id, 'exception' => $exception]);

            throw $exception;
        }
    }

    public function imageDataUri(?string $path): ?string
    {
        try {
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
        } catch (Throwable $exception) {
            Log::warning('Reading document image failed.', ['path' => $path, 'exception' => $exception]);

            return null;
        }
    }

    private function baseContext(Exam $exam): array
    {
        $exam->loadMissing(['subject', 'roomAssignments.room', 'roomAssignments.room.observers']);
        $school = School::query()->findOrFail($exam->school_id);

        return [
            'school' => $school,
            'letterhead' => $this->imageDataUri($school->letterhead_path),
            'exam' => $exam,
        ];
    }

    private function participantsByRoom(Exam $exam)
    {
        $roomIds = $exam->roomAssignments->pluck('room_id');
        $assignments = StudentRoomAssignment::query()
            ->where('school_id', $exam->school_id)
            ->whereIn('room_id', $roomIds)
            ->with(['student.classroom', 'room'])
            ->get()
            ->groupBy('room_id');

        return $exam->roomAssignments->sortBy('room.name')->values()->map(function ($assignment) use ($assignments): array {
            $observer = $assignment->room->observers->first();

            return [
                'id' => $assignment->room->id,
                'name' => $assignment->room->name,
                'observer_name' => $observer?->name ?? '-',
                'participants' => $assignments->get($assignment->room_id, collect())
                    ->sortBy(fn ($item) => $item->student->name)
                    ->values()
                    ->map(fn ($item): array => [
                        'id' => $item->student->id,
                        'nisn' => $item->student->nisn,
                        'name' => $item->student->name,
                        'classroom' => $item->student->classroom?->name ?? '-',
                    ])->all(),
            ];
        });
    }
}
