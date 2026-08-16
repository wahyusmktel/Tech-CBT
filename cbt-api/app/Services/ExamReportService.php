<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAnswer;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExamReportService
{
    public function build(Exam $exam): array
    {
        try {
            $exam->loadMissing(['subject', 'questionBank.questions.choices', 'credentials.student.classroom', 'credentials.attempt']);

            $results = $exam->credentials
                ->sortBy(fn ($credential) => $credential->student->name)
                ->values()
                ->map(function ($credential): array {
                    $attempt = $credential->attempt;

                    return [
                        'student_id' => $credential->student->id,
                        'nisn' => $credential->student->nisn,
                        'name' => $credential->student->name,
                        'classroom' => $credential->student->classroom?->name ?? '-',
                        'status' => $attempt?->status ?? 'not_started',
                        'score' => $attempt?->score !== null ? (float) $attempt->score : null,
                        'started_at' => $attempt?->started_at?->toIso8601String(),
                        'finished_at' => $attempt?->finished_at?->toIso8601String(),
                    ];
                });

            $finishedScores = $results->where('status', 'finished')->pluck('score')->filter(fn ($score) => $score !== null);
            $questionStats = $this->questionStats($exam);

            return [
                'exam' => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'subject' => $exam->subject->name,
                    'subject_code' => $exam->subject->code,
                    'start_at' => $exam->start_at->toIso8601String(),
                    'question_count' => $exam->questionBank?->questions->count() ?? 0,
                ],
                'summary' => [
                    'participant_count' => $results->count(),
                    'finished_count' => $results->where('status', 'finished')->count(),
                    'average_score' => $finishedScores->isNotEmpty() ? round($finishedScores->average(), 2) : null,
                    'highest_score' => $finishedScores->isNotEmpty() ? (float) $finishedScores->max() : null,
                    'lowest_score' => $finishedScores->isNotEmpty() ? (float) $finishedScores->min() : null,
                ],
                'results' => $results->all(),
                'question_analysis' => $questionStats,
            ];
        } catch (Throwable $exception) {
            Log::error('Building exam report failed.', ['exam_id' => $exam->id, 'exception' => $exception]);

            throw $exception;
        }
    }

    private function questionStats(Exam $exam): array
    {
        $finishedCount = $exam->attempts()->where('status', 'finished')->count();
        $aggregates = ExamAnswer::query()
            ->selectRaw('exam_answers.question_id, COUNT(*) as answered_count, SUM(CASE WHEN question_choices.is_correct = 1 THEN 1 ELSE 0 END) as correct_count')
            ->join('question_choices', 'question_choices.id', '=', 'exam_answers.question_choice_id')
            ->join('exam_attempts', 'exam_attempts.id', '=', 'exam_answers.attempt_id')
            ->where('exam_answers.school_id', $exam->school_id)
            ->where('exam_attempts.exam_id', $exam->id)
            ->where('exam_attempts.status', 'finished')
            ->groupBy('exam_answers.question_id')
            ->get()
            ->keyBy('question_id');

        return ($exam->questionBank?->questions ?? collect())->map(function ($question) use ($aggregates, $finishedCount): array {
            $aggregate = $aggregates->get($question->id);
            $answered = (int) ($aggregate?->answered_count ?? 0);
            $correct = (int) ($aggregate?->correct_count ?? 0);

            return [
                'question_id' => $question->id,
                'number' => $question->number,
                'text' => $question->text,
                'correct_answer' => $question->choices->firstWhere('is_correct', true)?->label ?? '-',
                'correct_count' => $correct,
                'wrong_count' => max(0, $answered - $correct),
                'unanswered_count' => max(0, $finishedCount - $answered),
                'correct_percentage' => $finishedCount > 0 ? round(($correct / $finishedCount) * 100, 2) : 0,
            ];
        })->all();
    }
}
