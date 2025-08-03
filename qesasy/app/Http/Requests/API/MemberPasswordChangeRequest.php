<?php
// ===== MemberPasswordChangeRequest.php =====

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class MemberPasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => [
                'required',
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
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'password.different' => 'New password must be different from current password.',
            'password.confirmed' => 'Password confirmation does not match.',
            'current_password.required' => 'Current password is required.',
        ];
    }
}
