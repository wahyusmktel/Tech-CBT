<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveExamAnswerRequest;
use App\Http\Requests\StudentExamLoginRequest;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamStudentCredential;
use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentExamController extends Controller
{
    public function login(StudentExamLoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $credential = ExamStudentCredential::query()->whereHas('exam', fn ($query) => $query->where('access_code', strtoupper($data['access_code'])))->where('username', $data['username'])->with(['exam', 'student'])->first();
            if (! $credential || ! hash_equals((string) $credential->password, $data['password'])) {
                return response()->json(['message' => 'Kode ujian, username, atau password tidak valid.'], 422);
            }
            if (! in_array($credential->exam->status->value, ['scheduled', 'active'], true)) {
                return response()->json(['message' => 'Ujian belum tersedia atau sudah selesai.'], 422);
            }
            $credential->tokens()->delete();
            $token = $credential->createToken('exam-session', ['exam:take'])->plainTextToken;

            return response()->json(['message' => 'Login peserta berhasil.', 'data' => ['token' => $token, 'user' => $this->userData($credential)]]);
        } catch (Throwable $e) {
            Log::error('Student exam login failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function session(Request $request): JsonResponse
    {
        try {
            return response()->json($this->userData($request->user()->load(['exam', 'student'])));
        } catch (Throwable $e) {
            Log::error('Loading student session failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function start(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $credential = $request->user()->load(['exam.questionBank.subject', 'exam.questionBank.questions.choices', 'student']);
            $exam = $credential->exam;
            if ($exam->status->value !== 'active') {
                DB::rollBack();

                return response()->json(['message' => 'Ujian belum dibuka oleh sekolah.'], 422);
            }
            if (! $exam->questionBank || ! $exam->questionBank->validated_at) {
                DB::rollBack();

                return response()->json(['message' => 'Paket soal ujian belum siap atau belum divalidasi.'], 422);
            }
            $endsAt = $exam->start_at->copy()->addMinutes($exam->duration_minutes);
            if (now()->greaterThanOrEqualTo($endsAt)) {
                DB::rollBack();

                return response()->json(['message' => 'Waktu ujian telah berakhir.'], 422);
            }
            $attempt = Cache::lock("exam-attempt:{$exam->id}:{$credential->student_id}", 10)->block(5, fn () => ExamAttempt::query()->firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $credential->student_id],
                ['school_id' => $exam->school_id, 'credential_id' => $credential->id, 'status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now()],
            ));
            $attempt->load('answers');
            DB::commit();

            return response()->json(['data' => $this->attemptData($credential, $attempt, $endsAt)]);
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Starting exam failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function saveAnswer(SaveExamAnswerRequest $request, string $question): JsonResponse
    {
        DB::beginTransaction();

        try {
            $credential = $request->user()->load('exam');
            $attempt = ExamAttempt::query()->where('credential_id', $credential->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== 'in_progress') {
                DB::rollBack();

                return response()->json(['message' => 'Ujian sudah diselesaikan.'], 409);
            }
            if (now()->greaterThanOrEqualTo($credential->exam->start_at->copy()->addMinutes($credential->exam->duration_minutes))) {
                DB::rollBack();

                return response()->json(['message' => 'Waktu ujian telah berakhir.'], 422);
            }
            $item = Question::query()->where('question_bank_id', $credential->exam->question_bank_id)->findOrFail($question);
            $choice = QuestionChoice::query()->where('question_id', $item->id)->findOrFail($request->validated('question_choice_id'));
            ExamAnswer::query()->updateOrCreate(['attempt_id' => $attempt->id, 'question_id' => $item->id], ['school_id' => $attempt->school_id, 'question_choice_id' => $choice->id, 'answered_at' => now()]);
            $attempt->update(['last_activity_at' => now()]);
            $savedAt = now();
            DB::commit();

            return response()->json(['message' => 'Jawaban tersimpan.', 'data' => ['saved_at' => $savedAt->toISOString()]]);
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($e instanceof ModelNotFoundException) {
                throw $e;
            }
            Log::error('Saving exam answer failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function submit(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $credential = $request->user()->load('exam');
            $attempt = ExamAttempt::query()->where('credential_id', $credential->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status === 'finished') {
                DB::rollBack();

                return response()->json(['message' => 'Ujian sudah diselesaikan.', 'data' => ['score' => $attempt->score]]);
            }
            $total = Question::query()->where('question_bank_id', $credential->exam->question_bank_id)->count();
            $correct = ExamAnswer::query()->where('attempt_id', $attempt->id)->whereHas('choice', fn ($query) => $query->where('is_correct', true))->count();
            $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
            $attempt->update(['status' => 'finished', 'finished_at' => now(), 'last_activity_at' => now(), 'score' => $score]);
            $credential->currentAccessToken()?->delete();
            DB::commit();

            return response()->json(['message' => 'Ujian berhasil diselesaikan.', 'data' => ['score' => $score]]);
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Submitting exam failed.', ['exception' => $e]);

            return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function userData(ExamStudentCredential $credential): array
    {
        return ['id' => $credential->id, 'role' => 'siswa', 'name' => $credential->student->name, 'student_id' => $credential->student_id, 'exam' => ['id' => $credential->exam->id, 'name' => $credential->exam->name, 'status' => $credential->exam->status->value, 'access_code' => $credential->exam->access_code]];
    }

    private function attemptData(ExamStudentCredential $credential, ExamAttempt $attempt, $endsAt): array
    {
        $bank = $credential->exam->questionBank;

        return ['attempt' => ['id' => $attempt->id, 'status' => $attempt->status, 'started_at' => $attempt->started_at->toISOString(), 'ends_at' => $endsAt->toISOString(), 'answers' => $attempt->answers->pluck('question_choice_id', 'question_id')], 'exam' => ['id' => $credential->exam->id, 'name' => $credential->exam->name, 'duration_minutes' => $credential->exam->duration_minutes, 'subject' => $bank->subject?->name], 'questions' => $bank->questions->map(fn ($question) => ['id' => $question->id, 'number' => $question->number, 'text' => $question->text, 'choices' => $question->choices->map(fn ($choice) => ['id' => $choice->id, 'label' => $choice->label, 'text' => $choice->text])])];
    }
}
