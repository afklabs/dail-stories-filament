<?php
// ===== MemberProfileUpdateRequest.php =====

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class MemberProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'min:2',
                'regex:/^[\p{L}\s\-\'\.]+$/u'
            ],
            'email' => [
                'sometimes',
                'string',
                'email:rfc,dns',
                'max:255',
                "unique:members,email,{$userId}",
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{1,14}$/'
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01',
                'date_format:Y-m-d'
            ],
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'current_password' => 'required_with:new_password|string',
            'new_password' => [
                'nullable',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and dots.',
            'email.unique' => 'This email address is already in use.',
            'phone.regex' => 'Please provide a valid phone number in international format.',
            'new_password.regex' => 'New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'new_password.different' => 'New password must be different from current password.',
            'current_password.required_with' => 'Current password is required when changing password.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        if ($this->has('phone') && $this->phone) {
            $this->merge(['phone' => preg_replace('/[^\d+]/', '', $this->phone)]);
        }
    }
}
