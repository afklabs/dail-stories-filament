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
                'regex:/^[A-Za-z\d]+$/'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل من حروف وأرقام',
            'password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
        ];
    }
}
