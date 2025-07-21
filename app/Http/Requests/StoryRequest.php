<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handle authorization in policies if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $storyId = $this->route('story')?->id;

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                'min:10',
            ],
            'content' => [
                'required',
                'string',
                'min:100',
            ],
            'excerpt' => [
                'nullable',
                'string',
                'max:300',
            ],
            'category_id' => [
                'required',
                'exists:categories,id',
            ],
            'tags' => [
                'nullable',
                'array',
                'max:10', // Maximum 10 tags
            ],
            'tags.*' => [
                'exists:tags,id',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB max
                'dimensions:min_width=400,min_height=300',
            ],
            'active' => [
                'boolean',
            ],
            'active_from' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'active_until' => [
                'nullable',
                'date',
                'after:active_from',
            ],
            'reading_time_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:120',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The story title is required.',
            'title.min' => 'The story title must be at least 10 characters.',
            'title.max' => 'The story title must not exceed 255 characters.',
            
            'content.required' => 'The story content is required.',
            'content.min' => 'The story content must be at least 100 characters.',
            
            'excerpt.max' => 'The excerpt must not exceed 300 characters.',
            
            'category_id.required' => 'Please select a category for this story.',
            'category_id.exists' => 'The selected category is invalid.',
            
            'tags.max' => 'You can select a maximum of 10 tags.',
            'tags.*.exists' => 'One or more selected tags are invalid.',
            
            'image.image' => 'The uploaded file must be an image.',
            'image.mimes' => 'The image must be a JPEG, PNG, JPG, or WebP file.',
            'image.max' => 'The image size must not exceed 2MB.',
            'image.dimensions' => 'The image must be at least 400x300 pixels.',
            
            'active_from.after_or_equal' => 'The publish date must be today or in the future.',
            'active_until.after' => 'The expiry date must be after the publish date.',
            
            'reading_time_minutes.min' => 'Reading time must be at least 1 minute.',
            'reading_time_minutes.max' => 'Reading time must not exceed 120 minutes.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'story title',
            'content' => 'story content',
            'excerpt' => 'story excerpt',
            'category_id' => 'category',
            'tags' => 'tags',
            'image' => 'story image',
            'active' => 'publication status',
            'active_from' => 'publish date',
            'active_until' => 'expiry date',
            'reading_time_minutes' => 'reading time',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and prepare title
        if ($this->has('title')) {
            $this->merge([
                'title' => trim($this->title),
            ]);
        }

        // Auto-generate excerpt if not provided
        if ($this->has('content') && (!$this->has('excerpt') || empty($this->excerpt))) {
            $plainText = strip_tags($this->content);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $excerpt = substr(trim($plainText), 0, 160);
            
            if (strlen(trim($plainText)) > 160) {
                $excerpt .= '...';
            }
            
            $this->merge([
                'excerpt' => $excerpt,
            ]);
        }

        // Auto-calculate reading time if not provided
        if ($this->has('content') && (!$this->has('reading_time_minutes') || empty($this->reading_time_minutes))) {
            $wordCount = str_word_count(strip_tags($this->content));
            $readingTime = max(1, ceil($wordCount / 200)); // 200 words per minute
            
            $this->merge([
                'reading_time_minutes' => $readingTime,
            ]);
        }

        // Set default values for checkboxes
        $this->merge([
            'active' => $this->boolean('active'),
        ]);

        // Set default active_from if publishing and not provided
        if ($this->active && (!$this->has('active_from') || empty($this->active_from))) {
            $this->merge([
                'active_from' => now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional custom validation logic
            
            // Validate content quality (optional)
            if ($this->has('content')) {
                $contentLength = strlen(strip_tags($this->content));
                $wordCount = str_word_count(strip_tags($this->content));
                
                if ($wordCount < 50) {
                    $validator->errors()->add(
                        'content', 
                        'The story content should contain at least 50 words for better quality.'
                    );
                }
            }

            // Validate scheduling logic
            if ($this->active && $this->has('active_from') && $this->has('active_until')) {
                if ($this->active_from && $this->active_until) {
                    $publishDate = \Carbon\Carbon::parse($this->active_from);
                    $expiryDate = \Carbon\Carbon::parse($this->active_until);
                    
                    // Check if the duration is reasonable (not too short)
                    if ($publishDate->diffInHours($expiryDate) < 1) {
                        $validator->errors()->add(
                            'active_until', 
                            'The story should be published for at least 1 hour.'
                        );
                    }
                    
                    // Check if the duration is not too long (optional business rule)
                    if ($publishDate->diffInDays($expiryDate) > 365) {
                        $validator->errors()->add(
                            'active_until', 
                            'The story cannot be published for more than one year.'
                        );
                    }
                }
            }
        });
    }
}