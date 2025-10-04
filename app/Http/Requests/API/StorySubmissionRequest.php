<?php

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorySubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'story_title' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],
            'story_content' => [
                'required',
                'string',
                'min:100',
                'max:50000',
            ],
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],
            'terms_accepted' => [
                'required',
                'boolean',
                'accepted',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'story_title.required' => 'عنوان القصة مطلوب',
            'story_title.min' => 'عنوان القصة يجب أن يكون على الأقل 5 أحرف',
            'story_title.max' => 'عنوان القصة يجب ألا يتجاوز 255 حرف',

            'story_content.required' => 'محتوى القصة مطلوب',
            'story_content.min' => 'القصة يجب أن تكون على الأقل 100 حرف',
            'story_content.max' => 'القصة يجب ألا تتجاوز 50000 حرف',

            'category_id.required' => 'التصنيف مطلوب',
            'category_id.exists' => 'التصنيف المختار غير صحيح',

            'terms_accepted.required' => 'يجب الموافقة على الشروط والأحكام',
            'terms_accepted.accepted' => 'يجب الموافقة على الشروط والأحكام',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
