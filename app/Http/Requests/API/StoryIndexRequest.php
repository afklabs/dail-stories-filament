<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'per_page' => 'integer|min:1|max:20',
            'page' => 'integer|min:1',
            'category_id' => 'integer|exists:categories,id',
            'search' => 'string|max:100|regex:/^[a-zA-Z0-9\s\-\_\.\,\!\?\@\#\$\%\&\*\(\)]+$/u',
            'sort_by' => Rule::in(['created_at', 'views', 'title', 'rating']),
            'sort_order' => Rule::in(['asc', 'desc']),
        ];
    }

    public function messages(): array
    {
        return [
            'search.regex' => 'Search contains invalid characters',
            'per_page.max' => 'Maximum 20 items per page allowed',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sanitize search input
        if ($this->has('search')) {
            $this->merge([
                'search' => strip_tags(trim($this->search)),
            ]);
        }

        // Ensure integer values
        if ($this->has('per_page')) {
            $this->merge([
                'per_page' => (int) $this->per_page,
            ]);
        }
    }
}
