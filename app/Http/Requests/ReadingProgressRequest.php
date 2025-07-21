<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReadingProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'progress' => 'required|integer|min:0|max:100',
            'time_spent' => 'nullable|integer|min:0',
        ];
    }
}