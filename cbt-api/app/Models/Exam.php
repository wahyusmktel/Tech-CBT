<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasUuids;

    protected $fillable = ['school_id', 'subject_id', 'question_bank_id', 'name', 'access_code', 'start_at', 'duration_minutes', 'status', 'credentials_generated_at'];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'credentials_generated_at' => 'datetime', 'status' => ExamStatus::class, 'duration_minutes' => 'integer'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ExamStudentCredential::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
