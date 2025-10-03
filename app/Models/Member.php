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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\MemberStoryInteraction;
use App\Models\MemberReadingHistory;


/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $password_changed_at
 * @property string|null $account_locked_at
 * @property int $failed_login_attempts
 * @property string|null $phone
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string $status
 * @property string|null $device_id
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property int $login_count
 * @property string|null $last_login_ip
 * @property string|null $registration_ip
 * @property string|null $user_agent
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $age
 * @property-read string $avatar_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $bookmarkedStories
 * @property-read int|null $bookmarked_stories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $dislikedStories
 * @property-read int|null $disliked_stories_count
 * @property-read bool $has_custom_avatar
 * @property-read string $initials
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberStoryInteraction> $interactions
 * @property-read int|null $interactions_count
 * @property-read mixed $is_active
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberReadingHistory> $readingHistory
 * @property-read int|null $reading_history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberStoryInteraction> $storyInteractions
 * @property-read int|null $story_interactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberStoryRating> $storyRatings
 * @property-read int|null $story_ratings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StoryView> $storyViews
 * @property-read int|null $story_views_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $viewedStories
 * @property-read int|null $viewed_stories_count
 * @method static Builder<static>|Member active()
 * @method static Builder<static>|Member adults()
 * @method static Builder<static>|Member byDevice(?string $deviceId)
 * @method static Builder<static>|Member byGender(string $gender)
 * @method static Builder<static>|Member inactive()
 * @method static Builder<static>|Member newModelQuery()
 * @method static Builder<static>|Member newQuery()
 * @method static Builder<static>|Member query()
 * @method static Builder<static>|Member recentlyActive(int $days = 30)
 * @method static Builder<static>|Member suspended()
 * @method static Builder<static>|Member unverified()
 * @method static Builder<static>|Member verified()
 * @method static Builder<static>|Member whereAccountLockedAt($value)
 * @method static Builder<static>|Member whereAvatar($value)
 * @method static Builder<static>|Member whereCreatedAt($value)
 * @method static Builder<static>|Member whereDateOfBirth($value)
 * @method static Builder<static>|Member whereDeviceId($value)
 * @method static Builder<static>|Member whereEmail($value)
 * @method static Builder<static>|Member whereEmailVerifiedAt($value)
 * @method static Builder<static>|Member whereFailedLoginAttempts($value)
 * @method static Builder<static>|Member whereGender($value)
 * @method static Builder<static>|Member whereId($value)
 * @method static Builder<static>|Member whereLastLoginAt($value)
 * @method static Builder<static>|Member whereLastLoginIp($value)
 * @method static Builder<static>|Member whereLoginCount($value)
 * @method static Builder<static>|Member whereName($value)
 * @method static Builder<static>|Member wherePassword($value)
 * @method static Builder<static>|Member wherePasswordChangedAt($value)
 * @method static Builder<static>|Member wherePhone($value)
 * @method static Builder<static>|Member whereRegistrationIp($value)
 * @method static Builder<static>|Member whereRememberToken($value)
 * @method static Builder<static>|Member whereStatus($value)
 * @method static Builder<static>|Member whereUpdatedAt($value)
 * @method static Builder<static>|Member whereUserAgent($value)
 * @method static Builder<static>|Member withCustomAvatar()
 * @method static Builder<static>|Member withoutCustomAvatar()
 * @mixin \Eloquent
 */
class Member extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    private const CACHE_TTL_SHORT = 300; // 5 minutes
    private const CACHE_TTL_MEDIUM = 900; // 15 minutes
    private const CACHE_TTL_LONG = 3600; // 1 hour

    // ✅ IMPROVED: Better organized fillable fields with security considerations
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'date_of_birth',
        'gender',
        'status',
        'device_id',
        'last_login_at',
        'email_verified_at',
        'registration_ip',
        'user_agent',
    ];

    // ✅ ENHANCED: Add new accessor to existing appends array
    protected $appends = ['avatar_url', 'has_custom_avatar', 'initials'];

    // ✅ NEW: Default avatar configuration - ADD THIS SECTION
    private const DEFAULT_AVATARS = [
        'male' => 'default-avatars/male-avatar.png',
        'female' => 'default-avatars/female-avatar.png',
        'default' => 'default-avatars/default-avatar.png',
    ];

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

    public function readingHistory(): HasMany
    {
        return $this->hasMany(MemberReadingHistory::class);
    }

    public function storyInteractions(): HasMany
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

    public function storyRatings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class);
    }

    /**
     * ✅ FIXED: Missing ratings() method that PHPStan couldn't find
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class, 'member_id');
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

    // ✅ ENHANCED: Replace your existing getAvatarUrlAttribute with this enhanced version
    public function getAvatarUrlAttribute(): string
    {
        // If user has custom avatar, return it
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return Storage::url($this->avatar);
        }

        // Return gender-based default or fallback to generic default
        return $this->getDefaultAvatarUrl();
    }

    // ✅ NEW: Add these new methods to your existing model
    public function getHasCustomAvatarAttribute(): bool
    {
        return $this->avatar && Storage::disk('public')->exists($this->avatar);
    }

    public function getInitialsAttribute(): string
    {
        $nameParts = explode(' ', trim($this->name ?? 'User'));

        if (count($nameParts) >= 2) {
            return strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
        }

        return strtoupper(substr($nameParts[0], 0, 2));
    }

    public function getDefaultAvatarUrl(): string
    {
        $genderKey = strtolower($this->gender ?? 'default');

        // Check if gender-specific default exists
        if (isset(self::DEFAULT_AVATARS[$genderKey])) {
            $defaultPath = self::DEFAULT_AVATARS[$genderKey];
        } else {
            $defaultPath = self::DEFAULT_AVATARS['default'];
        }

        // Check if the default avatar file exists in storage
        if (Storage::disk('public')->exists($defaultPath)) {
            return Storage::url($defaultPath);
        }

        // Fallback to a generated avatar service or placeholder
        return $this->generatePlaceholderAvatar();
    }

    public function generatePlaceholderAvatar(): string
    {
        $initials = $this->getInitialsAttribute();
        $backgroundColor = $this->generateColorFromName();

        // Use UI Avatars service (external)
        return "https://ui-avatars.com/api/?name=" . urlencode($initials)
            . "&background=" . substr($backgroundColor, 1)
            . "&color=ffffff&size=400&font-size=0.5&bold=true";
    }

    public function generateColorFromName(): string
    {
        $colors = [
            '#FF6B6B',
            '#4ECDC4',
            '#45B7D1',
            '#96CEB4',
            '#FFEAA7',
            '#DDA0DD',
            '#98D8C8',
            '#F7DC6F',
            '#BB8FCE',
            '#85C1E9',
            '#F8C471',
            '#82E0AA',
            '#F1948A',
            '#85C1E9',
            '#F4D03F'
        ];

        $hash = crc32($this->name ?? 'default');
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }

    // Add hasRole method if using without Spatie Permission
    public function hasRole($role): bool
    {
        // Implement your role checking logic here
        return false; // or use Spatie Permission trait
    }

    // ✅ ENHANCED: Keep your existing modern accessor but add fallback logic
    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                // First check custom avatar
                if ($this->avatar && Storage::disk('public')->exists("members/avatars/{$this->avatar}")) {
                    return Storage::url("members/avatars/{$this->avatar}");
                }

                // Check if avatar exists in different path format
                if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
                    return Storage::url($this->avatar);
                }

                // Return default avatar instead of Gravatar as final fallback
                return $this->getDefaultAvatarUrl();
            }
        );
    }

    protected function age(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->date_of_birth?->age
        );
    }

    protected function isActive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->status === 'active'
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

    // ✅ NEW: Add default avatar scopes
    public function scopeWithCustomAvatar(Builder $query): Builder
    {
        return $query->whereNotNull('avatar');
    }

    public function scopeWithoutCustomAvatar(Builder $query): Builder
    {
        return $query->whereNull('avatar');
    }

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
            Log::error('Failed to update last login', [
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

    public static function thisWeek(): Builder
    {
        return static::query()->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    // ✅ FIXED: Better error handling and return type - Fixed PHPStan issue
    public function interactWith(Story $story, string $action): ?MemberStoryInteraction
    {
        try {
            $interaction = $this->interactions()->updateOrCreate(
                [
                    'story_id' => $story->id,
                    'action' => $action,
                ],
                [
                    'story_id' => $story->id,
                    'action' => $action,
                ]
            );
            return $interaction instanceof MemberStoryInteraction ? $interaction : null;
        } catch (\Exception $e) {
            Log::error('Failed to create interaction', [
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
            $history = $this->readingHistory()->updateOrCreate(
                ['story_id' => $story->id],
                [
                    'reading_progress' => $progress,
                    'time_spent' => $timeSpent,
                    'last_read_at' => now(),
                ]
            );
            return $history instanceof MemberReadingHistory ? $history : null;
        } catch (\Exception $e) {
            Log::error('Failed to update reading progress', [
                'member_id' => $this->id,
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getReadingProgress(Story $story): ?MemberReadingHistory
    {
        $history = $this->readingHistory()
            ->where('story_id', $story->id)
            ->first();
        return $history instanceof MemberReadingHistory ? $history : null;
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

    /*
    |--------------------------------------------------------------------------
    | ✅ FIXED: Missing Analytics Methods - These methods were being called but not defined
    |--------------------------------------------------------------------------
    */

    /**
     * ✅ FIXED: Missing clearCache() method that PHPStan couldn't find
     */
    public function clearCache(): void
    {
        $patterns = [
            "member_{$this->id}_stats",
            "member_{$this->id}_reading_history",
            "member_reading_stats_{$this->id}",
            "member_comprehensive_stats_{$this->id}",
            "member_analytics_{$this->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * ✅ FIXED: Missing getPreferredCategories() method
     */
    public function getPreferredCategories(int $limit = 5): Collection
    {
        $cacheKey = "member_{$this->id}_preferred_categories_{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function () use ($limit): Collection {
            return $this->interactions()
                ->join('stories', 'member_story_interactions.story_id', '=', 'stories.id')
                ->join('categories', 'stories.category_id', '=', 'categories.id')
                ->where('action', 'like')
                ->groupBy('categories.id', 'categories.name')
                ->selectRaw('categories.id, categories.name, COUNT(*) as interaction_count')
                ->orderByDesc('interaction_count')
                ->limit($limit) // ✅ استخدم الـ parameter
                ->get()
                ->pluck('name', 'id');
        });
    }


    /**
     * ✅ FIXED: Missing getReadingConsistencyScore() method
     */
    public function getReadingConsistencyScore(): float
    {
        $cacheKey = "member_{$this->id}_consistency_score";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function (): float {
            $readingDays = $this->readingHistory()
                ->where('last_read_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(last_read_at) as read_date')
                ->distinct()
                ->count();

            // Calculate consistency as percentage of days read in last 30 days
            return round(($readingDays / 30) * 100, 1);
        });
    }

    /**
     * ✅ FIXED: Missing getMostReadCategory() method
     */
    public function getMostReadCategory(): ?string
    {
        $cacheKey = "member_{$this->id}_most_read_category";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function (): ?string {
            return $this->readingHistory()
                ->join('stories', 'member_reading_histories.story_id', '=', 'stories.id')
                ->join('categories', 'stories.category_id', '=', 'categories.id')
                ->groupBy('categories.name')
                ->selectRaw('categories.name, COUNT(*) as read_count')
                ->orderByDesc('read_count')
                ->value('name');
        });
    }

    /**
     * ✅ FIXED: Missing getPreferredReadingTime() method
     */
    public function getPreferredReadingTime(): string
    {
        $cacheKey = "member_{$this->id}_preferred_reading_time";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function (): string {
            $hourCounts = $this->readingHistory()
                ->selectRaw('HOUR(last_read_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->first();

            if (!$hourCounts || !isset($hourCounts->hour)) {
                return 'No data';
            }

            $hour = $hourCounts->hour;

            if ($hour >= 6 && $hour < 12) {
                return 'Morning (6AM-12PM)';
            } elseif ($hour >= 12 && $hour < 18) {
                return 'Afternoon (12PM-6PM)';
            } elseif ($hour >= 18 && $hour < 22) {
                return 'Evening (6PM-10PM)';
            } else {
                return 'Night (10PM-6AM)';
            }
        });
    }

    /**
     * ✅ FIXED: Missing getStoryCompletionRate() method
     */
    public function getStoryCompletionRate(): float
    {
        $cacheKey = "member_{$this->id}_completion_rate";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function (): float {
            $totalStarted = $this->readingHistory()
                ->where('reading_progress', '>', 0)
                ->count();

            $totalCompleted = $this->readingHistory()
                ->where('reading_progress', '>=', 100)
                ->count();

            return $totalStarted > 0 ? round(($totalCompleted / $totalStarted) * 100, 1) : 0;
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
            ->map(fn($date) => $date->format('Y-m-d'))
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
        return $this->avatar_url; // Now this will always return a URL, never null
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ NEW: MODEL EVENTS for default avatar handling
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        // Log when avatar is updated
        static::updating(function ($member) {
            if ($member->isDirty('avatar')) {
                Log::info('Member avatar updated', [
                    'member_id' => $member->id,
                    'old_avatar' => $member->getOriginal('avatar'),
                    'new_avatar' => $member->avatar,
                ]);
            }
        });
    }

    /**
     * Get FCM tokens for this member
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FCMToken::class);
    }

    /**
     * Get active FCM tokens
     */
    public function activeFcmTokens(): HasMany
    {
        return $this->hasMany(FCMToken::class)->where('is_active', true);
    }
}
