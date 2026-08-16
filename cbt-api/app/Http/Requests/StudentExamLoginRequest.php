<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentExamLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['access_code' => ['required', 'string', 'max:12'], 'username' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:100']];
    }
}
