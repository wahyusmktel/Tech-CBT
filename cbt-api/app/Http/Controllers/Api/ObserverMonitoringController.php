<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamStudentCredential;
use App\Models\StudentRoomAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ObserverMonitoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $observer = $request->user();
            $studentIds = StudentRoomAssignment::query()->where('room_id', $observer->room_id)->pluck('student_id');
            $exams = Exam::query()->where('school_id', $observer->school_id)->whereIn('status', ['scheduled', 'active'])
                ->whereHas('roomAssignments', fn ($query) => $query->where('room_id', $observer->room_id))
                ->with(['subject', 'questionBank' => fn ($query) => $query->withCount('questions')])->orderBy('start_at')->get();
            $credentialsByExam = ExamStudentCredential::query()->whereIn('exam_id', $exams->pluck('id'))->whereIn('student_id', $studentIds)
                ->with(['student.classroom', 'attempt' => fn ($query) => $query->withCount('answers'), 'tokens:id,tokenable_id,tokenable_type'])->get()->groupBy('exam_id');
            $data = $exams->map(function (Exam $exam) use ($credentialsByExam): array {
                $questionCount = $exam->questionBank?->questions_count ?? 0;
                $credentials = $credentialsByExam->get($exam->id, collect());

                return ['id' => $exam->id, 'name' => $exam->name, 'subject' => $exam->subject->name, 'status' => $exam->status->value, 'start_at' => $exam->start_at->toISOString(), 'question_count' => $questionCount, 'participants' => $credentials->map(function ($credential) use ($questionCount): array {
                    $attempt = $credential->attempt;
                    $status = $attempt?->status === 'finished' ? 'finished' : ($attempt ? 'in_progress' : ($credential->tokens->isNotEmpty() ? 'logged_in' : 'not_logged_in'));

                    return ['id' => $credential->student->id, 'nisn' => $credential->student->nisn, 'name' => $credential->student->name, 'classroom' => $credential->student->classroom->name, 'status' => $status, 'answered' => $attempt?->answers_count ?? 0, 'total' => $questionCount, 'last_activity_at' => $attempt?->last_activity_at?->toISOString()];
                })->values()];
            });

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            Log::error('Loading observer monitoring failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }
}
