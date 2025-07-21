<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoryInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['like', 'dislike', 'bookmark', 'share', 'report'])],
            'metadata' => 'nullable|array',
        ];
    }
}