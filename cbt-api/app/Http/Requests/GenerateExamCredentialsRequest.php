<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateExamCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['username_strategy' => ['required', Rule::in(['nisn', 'random'])], 'password_type' => ['required', Rule::in(['numeric', 'letters', 'mixed'])], 'password_length' => ['required', 'integer', 'min:6', 'max:20'], 'force' => ['sometimes', 'boolean']];
    }
}
