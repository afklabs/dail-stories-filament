<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class StoryRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            // Ignore these fields from Flutter
            'member_id' => 'sometimes|integer',
            'device_id' => 'sometimes|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'التقييم مطلوب',
            'rating.integer' => 'التقييم يجب أن يكون رقماً صحيحاً',
            'rating.min' => 'التقييم يجب أن يكون على الأقل 1',
            'rating.max' => 'التقييم يجب أن لا يتجاوز 5',
            'comment.string' => 'التعليق يجب أن يكون نصاً',
            'comment.max' => 'التعليق يجب أن لا يتجاوز 1000 حرف',
        ];
    }

    /**
     * Prepare the data for validation.
     * Remove fields we don't need before validation
     */
    protected function prepareForValidation(): void
    {
        // Clean the comment
        if ($this->has('comment') && !empty(trim($this->comment))) {
            $this->merge([
                'comment' => trim($this->comment)
            ]);
        } else {
            $this->merge([
                'comment' => null
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     * Only return what we need
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Only return rating and comment
        return [
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ];
    }
}
