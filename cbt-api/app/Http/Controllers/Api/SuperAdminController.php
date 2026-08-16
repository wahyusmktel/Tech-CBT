<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListSuperAdminSchoolsRequest;
use App\Http\Requests\SuperAdminSchoolRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SuperAdminController extends Controller
{
    public function index(ListSuperAdminSchoolsRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', School::class);
            $data = $request->validated();
            $schools = School::query()
                ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->withCount(['students', 'subjects', 'exams', 'attempts'])
                ->withAvg('attempts', 'score')
                ->with(['users' => fn ($query) => $query->where('role', UserRole::Kurikulum)->select('id', 'school_id', 'name', 'username', 'email', 'is_active')])
                ->latest()
                ->paginate($data['per_page'] ?? 10);

            return response()->json([
                'data' => [
                    'summary' => Cache::remember('super-admin:dashboard-summary', now()->addSeconds(30), fn (): array => $this->summary()),
                    'schools' => $schools->getCollection()->map(fn (School $school): array => $this->schoolRow($school))->all(),
                ],
                'meta' => ['current_page' => $schools->currentPage(), 'last_page' => $schools->lastPage(), 'per_page' => $schools->perPage(), 'total' => $schools->total()],
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading super admin dashboard failed.');
        }
    }

    public function show(SuperAdminSchoolRequest $request, string $school): JsonResponse
    {
        try {
            $schoolModel = School::query()->findOrFail($school);
            $this->authorize('view', $schoolModel);

            $schoolModel->loadCount(['students', 'subjects', 'exams', 'attempts'])
                ->load(['users' => fn ($query) => $query->where('role', UserRole::Kurikulum)->select('id', 'school_id', 'name', 'username', 'email', 'is_active')]);

            $subjectScores = ExamAttempt::query()
                ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
                ->where('exam_attempts.school_id', $schoolModel->id)
                ->where('exam_attempts.status', 'finished')
                ->selectRaw('exams.subject_id, COUNT(*) as attempts_count, AVG(exam_attempts.score) as average_score')
                ->groupBy('exams.subject_id')->get()->keyBy('subject_id');

            $subjects = Subject::query()->where('school_id', $schoolModel->id)->withCount('exams')->orderBy('name')->get()
                ->map(function (Subject $subject) use ($subjectScores): array {
                    $score = $subjectScores->get($subject->id);

                    return ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name, 'exams_count' => $subject->exams_count, 'finished_attempts_count' => (int) ($score?->attempts_count ?? 0), 'average_score' => $score ? round((float) $score->average_score, 2) : null];
                });

            $recentExams = Exam::query()->where('school_id', $schoolModel->id)->with('subject')
                ->withCount(['attempts', 'attempts as finished_count' => fn ($query) => $query->where('status', 'finished')])
                ->withAvg(['attempts as average_score' => fn ($query) => $query->where('status', 'finished')], 'score')
                ->latest('start_at')->limit(10)->get()
                ->map(fn (Exam $exam): array => ['id' => $exam->id, 'name' => $exam->name, 'subject' => $exam->subject->name, 'status' => $exam->status->value, 'start_at' => $exam->start_at->toIso8601String(), 'attempts_count' => $exam->attempts_count, 'finished_count' => $exam->finished_count, 'average_score' => $exam->average_score !== null ? round((float) $exam->average_score, 2) : null]);

            $recentScores = ExamAttempt::query()->where('school_id', $schoolModel->id)->where('status', 'finished')
                ->with(['student.classroom', 'exam.subject'])->latest('finished_at')->limit(10)->get()
                ->map(fn (ExamAttempt $attempt): array => ['id' => $attempt->id, 'student' => $attempt->student->name, 'nisn' => $attempt->student->nisn, 'classroom' => $attempt->student->classroom?->name ?? '-', 'exam' => $attempt->exam->name, 'subject' => $attempt->exam->subject->name, 'score' => (float) $attempt->score, 'finished_at' => $attempt->finished_at?->toIso8601String()]);

            return response()->json(['data' => [
                'school' => $this->schoolRow($schoolModel),
                'subjects' => $subjects,
                'recent_exams' => $recentExams,
                'recent_scores' => $recentScores,
            ]]);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Loading super admin school detail failed.');
        }
    }

    public function resetCurriculumPassword(SuperAdminSchoolRequest $request, string $school): JsonResponse
    {
        DB::beginTransaction();

        try {
            $schoolModel = School::query()->findOrFail($school);
            $this->authorize('resetCurriculumPassword', $schoolModel);
            $curriculum = User::query()->where('school_id', $schoolModel->id)->where('role', UserRole::Kurikulum)->oldest()->firstOrFail();
            $temporaryPassword = Str::password(12, letters: true, numbers: true, symbols: false);

            $curriculum->update(['password' => $temporaryPassword, 'generated_password' => $temporaryPassword, 'is_active' => true]);
            $curriculum->tokens()->delete();
            SuperAdminAuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'school_id' => $schoolModel->id,
                'action' => 'curriculum_password_reset',
                'metadata' => ['target_user_id' => $curriculum->id, 'username' => $curriculum->username],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
            DB::commit();
            Cache::forget('super-admin:dashboard-summary');

            return response()->json(['message' => 'Password Kurikulum berhasil direset.', 'data' => ['username' => $curriculum->username, 'temporary_password' => $temporaryPassword]]);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return $this->failure($exception, 'Resetting curriculum password failed.');
        }
    }

    private function summary(): array
    {
        $finishedAttempts = ExamAttempt::query()->where('status', 'finished');

        return [
            'schools_count' => School::query()->count(),
            'students_count' => Student::query()->count(),
            'subjects_count' => Subject::query()->count(),
            'exams_count' => Exam::query()->count(),
            'finished_attempts_count' => (clone $finishedAttempts)->count(),
            'average_score' => ($average = (clone $finishedAttempts)->avg('score')) !== null ? round((float) $average, 2) : null,
        ];
    }

    private function schoolRow(School $school): array
    {
        $curriculum = $school->users->first();

        return [
            'id' => $school->id,
            'name' => $school->name ?: 'Sekolah NPSN '.$school->npsn,
            'npsn' => $school->npsn,
            'type' => $school->type->value,
            'address' => $school->address,
            'email' => $school->email,
            'students_count' => $school->students_count,
            'subjects_count' => $school->subjects_count,
            'exams_count' => $school->exams_count,
            'attempts_count' => $school->attempts_count,
            'average_score' => isset($school->attempts_avg_score) && $school->attempts_avg_score !== null ? round((float) $school->attempts_avg_score, 2) : null,
            'curriculum' => $curriculum ? ['id' => $curriculum->id, 'name' => $curriculum->name, 'username' => $curriculum->username, 'email' => $curriculum->email, 'is_active' => $curriculum->is_active] : null,
            'created_at' => $school->created_at?->toIso8601String(),
        ];
    }

    private function failure(Throwable $exception, string $context): JsonResponse
    {
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
            throw $exception;
        }
        Log::error($context, ['exception' => $exception]);

        return response()->json(['message' => 'Terjadi kesalahan sistem.'], 500);
    }
}
