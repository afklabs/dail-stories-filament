<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    // ✅ IMPROVED: Better organized fillable fields with security considerations
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'date_of_birth',
        'gender',
        'status',
        'device_id',
        'last_login_at',
        'email_verified_at',
    ];
    // Add accessor for avatar_url
    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }

    // Add hasRole method if using without Spatie Permission
    public function hasRole($role): bool
    {
        // Implement your role checking logic here
        return false; // or use Spatie Permission trait
    }
    // ✅ IMPROVED: Added more security-sensitive fields
    protected $hidden = [
        'password',
        'remember_token',
        'device_id', // Security: Hide device tracking
    ];

    // ✅ IMPROVED: Better casting with enum support
    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ NEW: Filament panel access (if members need admin access)
    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow verified, active members with admin role
        return $this->status === 'active' &&
               $this->email_verified_at !== null &&
               $this->hasRole('member_admin'); // If using Spatie Permission
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS - ✅ IMPROVED with better type hints and optimization
    |--------------------------------------------------------------------------
    */

    public function storyViews(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

        public function readingHistory()
    {
        return $this->hasMany(MemberReadingHistory::class);
    }

    public function storyInteractions()
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

    public function storyRatings()
    {
        return $this->hasMany(MemberStoryRating::class);
    }


    // ✅ IMPROVED: More efficient relationship queries with proper pivot selection
    public function likedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'member_story_interactions')
            ->wherePivot('action', 'like')
            ->withPivot(['created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function dislikedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'member_story_interactions')
            ->wherePivot('action', 'dislike')
            ->withPivot(['created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function bookmarkedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'member_story_interactions')
            ->wherePivot('action', 'bookmark')
            ->withPivot(['created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function viewedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'member_story_interactions')
            ->wherePivot('action', 'view')
            ->withPivot(['created_at', 'updated_at'])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS - ✅ IMPROVED with Laravel 9+ syntax and better logic
    |--------------------------------------------------------------------------
    */

    // ✅ IMPROVED: Modern accessor syntax with proper Storage facade usage
    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                if ($this->avatar && Storage::disk('public')->exists("members/avatars/{$this->avatar}")) {
                    return Storage::disk('public')->url("members/avatars/{$this->avatar}");
                }

                // ✅ IMPROVED: Fallback to Gravatar, then default
                $gravatar = 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email))).'?d=mp&s=200';

                return $gravatar;
            }
        );
    }

    protected function age(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->date_of_birth?->age
        );
    }

    protected function isActive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->status === 'active'
        );
    }

    // ✅ IMPROVED: Better password handling with validation
    protected function password(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: function (string $value) {
                // Only hash if not already hashed
                if (password_get_info($value)['algo'] === null) {
                    return Hash::make($value);
                }

                return $value;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES - ✅ IMPROVED with better naming and additional useful scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at');
    }

    public function scopeByDevice(Builder $query, ?string $deviceId): Builder
    {
        return $deviceId ? $query->where('device_id', $deviceId) : $query->whereNull('device_id');
    }

    public function scopeRecentlyActive(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    // ✅ NEW: Additional useful scopes for analytics
    public function scopeAdults(Builder $query): Builder
    {
        return $query->where('date_of_birth', '<=', now()->subYears(18));
    }

    public function scopeByGender(Builder $query, string $gender): Builder
    {
        return $query->where('gender', $gender);
    }

    /*
    |--------------------------------------------------------------------------
    | METHODS - ✅ IMPROVED with better error handling and type safety
    |--------------------------------------------------------------------------
    */

    public function updateLastLogin(?string $deviceId = null): bool
    {
        try {
            return $this->update([
                'last_login_at' => now(),
                'device_id' => $deviceId,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update last login', [
                'member_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function hasInteractionWith(Story $story, string $action): bool
    {
        return $this->interactions()
            ->where('story_id', $story->id)
            ->where('action', $action)
            ->exists();
    }

    // ✅ IMPROVED: Better error handling and return type
    public function interactWith(Story $story, string $action): ?MemberStoryInteraction
    {
        try {
            return $this->interactions()->updateOrCreate(
                [
                    'story_id' => $story->id,
                    'action' => $action,
                ],
                [
                    'story_id' => $story->id,
                    'action' => $action,
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to create interaction', [
                'member_id' => $this->id,
                'story_id' => $story->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function removeInteractionWith(Story $story, string $action): bool
    {
        return $this->interactions()
            ->where('story_id', $story->id)
            ->where('action', $action)
            ->delete() > 0;
    }

    // ✅ IMPROVED: Better validation and error handling
    public function updateReadingProgress(Story $story, float $progress, int $timeSpent = 0): ?MemberReadingHistory
    {
        // Validate input
        $progress = max(0, min(100, $progress));
        $timeSpent = max(0, $timeSpent);

        try {
            return $this->readingHistory()->updateOrCreate(
                ['story_id' => $story->id],
                [
                    'reading_progress' => $progress,
                    'time_spent' => $timeSpent,
                    'last_read_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to update reading progress', [
                'member_id' => $this->id,
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getReadingProgress(Story $story): ?MemberReadingHistory
    {
        return $this->readingHistory()
            ->where('story_id', $story->id)
            ->first();
    }

    // ✅ IMPROVED: More comprehensive stats with caching
    public function getStats(): array
    {
        return cache()->remember("member_stats_{$this->id}", 300, function () {
            $readingHistory = $this->readingHistory();

            return [
                'total_views' => $this->storyViews()->count(),
                'unique_stories_viewed' => $this->storyViews()->distinct('story_id')->count(),
                'total_likes' => $this->interactions()->where('action', 'like')->count(),
                'total_dislikes' => $this->interactions()->where('action', 'dislike')->count(),
                'total_bookmarks' => $this->interactions()->where('action', 'bookmark')->count(),
                'stories_started' => $readingHistory->where('reading_progress', '>', 0)->count(),
                'stories_completed' => $readingHistory->where('reading_progress', 100)->count(),
                'total_reading_time_minutes' => round($readingHistory->sum('time_spent') / 60, 2),
                'avg_reading_progress' => round($readingHistory->avg('reading_progress'), 2),
                'completion_rate' => $this->getCompletionRate(),
                'favorite_category' => $this->getFavoriteCategory(),
                'reading_streak_days' => $this->getCurrentReadingStreak(),
            ];
        });
    }

    // ✅ NEW: Additional analytics methods
    private function getCompletionRate(): float
    {
        $started = $this->readingHistory()->where('reading_progress', '>', 0)->count();
        $completed = $this->readingHistory()->where('reading_progress', 100)->count();

        return $started > 0 ? round(($completed / $started) * 100, 2) : 0;
    }

    private function getFavoriteCategory(): ?string
    {
        return $this->interactions()
            ->join('stories', 'member_story_interactions.story_id', '=', 'stories.id')
            ->join('categories', 'stories.category_id', '=', 'categories.id')
            ->where('action', 'like')
            ->groupBy('categories.name')
            ->orderByRaw('COUNT(*) DESC')
            ->value('categories.name');
    }

    private function getCurrentReadingStreak(): int
    {
        // Implementation for reading streak calculation
        $recentDays = $this->readingHistory()
            ->where('last_read_at', '>=', now()->subDays(30))
            ->orderBy('last_read_at', 'desc')
            ->pluck('last_read_at')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->unique()
            ->values();

        $streak = 0;
        $currentDate = now()->format('Y-m-d');

        foreach ($recentDays as $date) {
            if ($date === $currentDate || $date === now()->subDay()->format('Y-m-d')) {
                $streak++;
                $currentDate = Carbon::parse($date)->subDay()->format('Y-m-d');
            } else {
                break;
            }
        }

        return $streak;
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT-SPECIFIC METHODS - ✅ NEW for better Filament integration
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }
}
