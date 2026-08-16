<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), ['school' => $this->route('school')]);
    }

    public function rules(): array
    {
        return ['school' => ['required', 'uuid']];
    }
}
