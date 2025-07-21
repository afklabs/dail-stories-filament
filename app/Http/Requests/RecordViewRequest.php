<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'reading_time' => 'nullable|integer|min:1|max:3600',
            'scroll_percentage' => 'nullable|integer|min:0|max:100',
        ];
    }
}