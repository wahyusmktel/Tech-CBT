<?php

namespace App\Http\Requests;

use App\Enums\SchoolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class RegisterSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'npsn' => ['required', 'string', 'max:20', 'unique:schools,npsn'],
            'school_type' => ['required', new Enum(SchoolType::class)],
            'address' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:schools,email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:4', 'max:60', 'alpha_dash:ascii', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
