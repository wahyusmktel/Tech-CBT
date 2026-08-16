<?php

namespace App\Http\Requests;

use App\Enums\SchoolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['required', 'string', 'max:20', Rule::unique('schools', 'npsn')->ignore($schoolId)],
            'type' => ['required', new Enum(SchoolType::class)],
            'address' => ['required', 'string', 'max:2000'],
            'principal_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]+$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('schools', 'email')->ignore($schoolId)],
            'letterhead' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda tambah, tanda kurung, dan tanda hubung.',
            'letterhead.max' => 'Ukuran Kop Surat maksimal 4 MB.',
        ];
    }
}
