<?php
// ===== MemberAccountDeletionRequest.php =====

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class MemberAccountDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE_MY_ACCOUNT',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'Please type "DELETE_MY_ACCOUNT" to confirm account deletion.',
            'reason.max' => 'Deletion reason cannot exceed 500 characters.',
            'password.required' => 'Password is required to confirm account deletion.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->merge(['reason' => trim($this->reason)]);
        }
    }
}