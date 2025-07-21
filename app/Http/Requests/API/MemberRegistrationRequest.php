<?php

// ===== MemberRegistrationRequest.php =====

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class MemberRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[\p{L}\s\-\'\.]+$/u' // Unicode letters, spaces, hyphens, apostrophes, dots
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:members,email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
            'device_id' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9\-_]+$/',
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{1,14}$/' // E.164 format
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01',
                'date_format:Y-m-d'
            ],
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and dots.',
            'email.regex' => 'Please provide a valid email address format.',
            'email.unique' => 'This email address is already registered.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'phone.regex' => 'Please provide a valid phone number in international format.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'date_of_birth.after' => 'Please provide a valid date of birth.',
            'device_id.regex' => 'Device ID contains invalid characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email ?? '')),
            'name' => trim($this->name ?? ''),
            'phone' => $this->phone ? preg_replace('/[^\d+]/', '', $this->phone) : null,
        ]);
    }
}
