<?php

namespace App\Http\Requests;

use App\Enums\ExamStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'question_bank_id' => ['nullable', 'uuid', Rule::exists('question_banks', 'id')->where('school_id', $schoolId)],
            'start_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', new Enum(ExamStatus::class)],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['uuid', 'distinct', Rule::exists('rooms', 'id')->where('school_id', $schoolId)],
        ];
    }
}
