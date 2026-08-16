<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MapRoomStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'classroom_ids' => ['present', 'array'],
            'classroom_ids.*' => ['uuid', 'distinct', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
            'student_ids' => ['present', 'array'],
            'student_ids.*' => ['uuid', 'distinct', Rule::exists('students', 'id')->where('school_id', $schoolId)],
        ];
    }
}
