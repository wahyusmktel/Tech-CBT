<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExamReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), ['exam' => $this->route('exam')]);
    }

    public function rules(): array
    {
        return [
            'exam' => ['required', 'uuid'],
        ];
    }
}
