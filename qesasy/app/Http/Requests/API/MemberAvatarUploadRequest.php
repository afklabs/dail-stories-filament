<?php
// ===== MemberAvatarUploadRequest.php =====

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class MemberAvatarUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB
                'dimensions:min_width=200,min_height=200,max_width=2000,max_height=2000'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Please select an avatar image.',
            'avatar.image' => 'Avatar must be a valid image file.',
            'avatar.mimes' => 'Avatar must be in JPEG, PNG, JPG, or WebP format.',
            'avatar.max' => 'Avatar file size cannot exceed 2MB.',
            'avatar.dimensions' => 'Avatar must be at least 200x200 pixels and at most 2000x2000 pixels.',
        ];
    }
}
