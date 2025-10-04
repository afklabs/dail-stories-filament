<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * Member Story Submission Model
 * 
 * @property int $id
 * @property int $member_id
 * @property string $story_title
 * @property string $story_content
 * @property int $category_id
 * @property string $submission_status
 * @property string|null $admin_notes
 * @property int|null $published_story_id
 * @property Carbon $submitted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Member $member
 * @property-read Category $category
 * @property-read Story|null $publishedStory
 * @property-read User|null $reviewer
 */
class MemberStorySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'story_title',
        'story_content',
        'category_id',
        'submission_status',
        'admin_notes',
        'published_story_id',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function publishedStory(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'published_story_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('submission_status', 'pending');
    }

    public function scopeArchived($query)
    {
        return $query->where('submission_status', 'archived');
    }

    public function scopePublished($query)
    {
        return $query->where('submission_status', 'published');
    }

    public function scopeRejected($query)
    {
        return $query->where('submission_status', 'rejected');
    }

    /*
    |--------------------------------------------------------------------------
    | METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Mark submission as published
     */
    public function markAsPublished(int $storyId, int $userId): bool
    {
        $this->submission_status = 'published';
        $this->published_story_id = $storyId;
        $this->reviewed_at = now();
        $this->reviewed_by = $userId;

        return $this->save();
    }

    /**
     * Mark submission as archived
     */
    public function markAsArchived(int $userId): bool
    {
        $this->submission_status = 'archived';
        $this->reviewed_at = now();
        $this->reviewed_by = $userId;

        return $this->save();
    }

    /**
     * Mark submission as rejected
     */
    public function markAsRejected(int $userId, ?string $reason = null): bool
    {
        $this->submission_status = 'rejected';
        $this->reviewed_at = now();
        $this->reviewed_by = $userId;

        if ($reason) {
            $this->admin_notes = $reason;
        }

        return $this->save();
    }
}
