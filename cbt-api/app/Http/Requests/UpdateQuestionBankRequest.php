<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return ['title' => ['required', 'string', 'max:255', Rule::unique('question_banks')->where(fn ($query) => $query->where('school_id', $schoolId)->where('subject_id', $this->input('subject_id')))->ignore($this->route('question_bank'))], 'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')->where('school_id', $schoolId)]];
    }
}
