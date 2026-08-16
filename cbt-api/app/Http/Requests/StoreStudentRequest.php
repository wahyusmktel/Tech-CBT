<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'nisn' => ['required', 'string', 'max:30', Rule::unique('students')->where('school_id', $schoolId)],
            'name' => ['required', 'string', 'max:255'],
            'classroom_id' => ['required', 'uuid', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
        ];
    }
}
