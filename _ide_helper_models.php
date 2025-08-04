<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $stories
 * @property-read int|null $stories_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member adults()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member byDevice(?string $deviceId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member byGender(string $gender)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member recentlyActive(int $days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member suspended()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member unverified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereAccountLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereFailedLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereLoginCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member wherePasswordChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereRegistrationIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member withCustomAvatar()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member withoutCustomAvatar()
 */
	class Member extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $member_id
 * @property int $story_id
 * @property numeric $reading_progress
 * @property int $time_spent
 * @property int $reading_sessions
 * @property array<array-key, mixed>|null $bookmarks
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $last_read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $is_completed
 * @property-read \App\Models\Member $member
 * @property-read mixed $progress_percentage
 * @property-read mixed $progress_status
 * @property-read \App\Models\Story $story
 * @property-read mixed $time_spent_hours
 * @property-read mixed $time_spent_minutes
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory byMember(int $memberId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory byProgress(float $min = 0, float $max = 100)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory byStory(int $storyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory highProgress()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory inProgress()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory longReads(int $minMinutes = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory multipleSessions()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory notStarted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory recentlyRead(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory started()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereBookmarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereLastReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereReadingProgress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereReadingSessions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereTimeSpent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberReadingHistory whereUpdatedAt($value)
 */
	class MemberReadingHistory extends \Eloquent {}
}

namespace App\Models{
/**
 * Member Story Interaction Model - Enhanced with Filament Integration
 *
 * @property int $id
 * @property int $member_id
 * @property int $story_id
 * @property string $action
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Member $member
 * @property-read Story $story
 * @property int $interaction_count
 * @property string|null $last_interacted_at
 * @property-read string $action_color
 * @property-read string $action_icon
 * @property-read string $action_label
 * @property-read bool $is_negative
 * @property-read bool $is_neutral
 * @property-read bool $is_positive
 * @property-read string|null $metadata_value
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction byAction(string $action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction byActions(array $actions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction byMember(int $memberId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction byStory(int $storyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction negative()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction neutral()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction positive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction thisMonth()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction thisWeek()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction today()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereInteractionCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereLastInteractedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryInteraction whereUpdatedAt($value)
 */
	class MemberStoryInteraction extends \Eloquent {}
}

namespace App\Models{
/**
 * Member Story Rating Model - Enhanced with Filament Integration
 *
 * @property int $id
 * @property int $member_id
 * @property int $story_id
 * @property int $rating
 * @property string|null $comment
 * @property bool $is_verified
 * @property int $helpful_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Member $member
 * @property-read Story $story
 * @property-read StoryRatingAggregate $aggregate
 * @property string|null $metadata
 * @property-read string|null $comment_excerpt
 * @property-read bool $has_comment
 * @property-read bool $is_high_rating
 * @property-read bool $is_low_rating
 * @property-read string $rating_color
 * @property-read string $rating_label
 * @property-read string $stars_display
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating byMember(int $memberId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating byRating(int $rating)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating byStory(int $storyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating highRatings()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating lowRatings()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating popular(int $minHelpfulCount = 5)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating unverified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereIsVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberStoryRating withComments()
 */
	class MemberStoryRating extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property string $group
 * @property string $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * Story Model for Daily Stories App with Filament Integration
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string|null $excerpt
 * @property string $slug
 * @property int $category_id
 * @property int $views
 * @property int $reading_time_minutes
 * @property bool $active
 * @property bool $is_featured
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property Carbon|null $published_at
 * @property string|null $image
 * @property int $previous_views
 * @property int $recent_views
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $status
 * @property-read Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|Tag[] $tags
 * @property-read \Illuminate\Database\Eloquent\Collection|StoryView[] $storyViews
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberStoryInteraction[] $interactions
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberStoryRating[] $ratings
 * @property-read StoryRatingAggregate|null $ratingAggregate
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberReadingHistory[] $readingHistory
 * @property-read \Illuminate\Database\Eloquent\Collection|StoryPublishingHistory[] $publishingHistory
 * @property string|null $author Story author name (المؤلف)
 * @property-read float $average_rating
 * @property-read int $calculated_reading_time_minutes
 * @property-read string $display_excerpt
 * @property-read string $formatted_reading_time
 * @property-read string $formatted_remaining_time
 * @property-read string $formatted_total_ratings
 * @property-read string $formatted_views
 * @property-read bool $has_expired
 * @property-read string|null $image_url
 * @property-read bool $is_expired
 * @property-read array|null $remaining_time
 * @property-read int $total_ratings
 * @property-read int|null $interactions_count
 * @property-read int|null $publishing_history_count
 * @property-read int|null $ratings_count
 * @property-read int|null $reading_history_count
 * @property-read int|null $story_views_count
 * @property-read int|null $tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story expiringSoon(int $hours = 24)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story featured()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story highRated(float $minRating = 4)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story inCategory(int $categoryId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story popular(int $minViews = 100)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereActiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereActiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereAuthor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereReadingTimeMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story whereViews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Story withTag(int $tagId)
 */
	class Story extends \Eloquent {}
}

namespace App\Models{
/**
 * StoryPublishingHistory Model for Daily Stories App with Filament Integration
 * 
 * Enhanced audit trail system for tracking story publishing activities
 * with comprehensive analytics and monitoring capabilities.
 *
 * @property int $id
 * @property int $story_id
 * @property int $user_id
 * @property string $action
 * @property bool|null $previous_active_status
 * @property bool|null $new_active_status
 * @property Carbon|null $previous_active_from
 * @property Carbon|null $previous_active_until
 * @property Carbon|null $new_active_from
 * @property Carbon|null $new_active_until
 * @property string|null $notes
 * @property array|null $changed_fields
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $action_color
 * @property-read string $action_icon
 * @property-read string $changes_summary
 * @property-read string $formatted_action
 * @property-read string $impact_level
 * @property-read string $schedule_change
 * @property-read string $status_change
 * @property-read \App\Models\Story $story
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory byAction(string $action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory byStory(int $storyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory byUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory highImpact()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory publishingActions()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory schedulingActions()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory thisWeek()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory today()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereChangedFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereNewActiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereNewActiveStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereNewActiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory wherePreviousActiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory wherePreviousActiveStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory wherePreviousActiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryPublishingHistory whereUserId($value)
 */
	class StoryPublishingHistory extends \Eloquent {}
}

namespace App\Models{
/**
 * StoryRatingAggregate Model for Daily Stories App with Filament Integration
 * 
 * Enhanced aggregate system for story ratings with comprehensive analytics,
 * sentiment analysis, and performance optimizations.
 *
 * @property int $id
 * @property int $story_id
 * @property int $total_ratings
 * @property int $sum_ratings
 * @property float $average_rating
 * @property array $rating_distribution
 * @property int|null $verified_ratings_count
 * @property float|null $verified_average_rating
 * @property int|null $comments_count
 * @property Carbon|null $last_rated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float $comments_percentage
 * @property-read float $high_rating_percentage
 * @property-read bool $is_high_quality
 * @property-read bool $is_reliable
 * @property-read float $low_rating_percentage
 * @property-read float $medium_rating_percentage
 * @property-read float $negative_rating_percentage
 * @property-read float $neutral_rating_percentage
 * @property-read float $positive_rating_percentage
 * @property-read float $quality_score
 * @property-read string $rating_level
 * @property-read array $rating_percentages
 * @property-read float $recommendation_rate
 * @property-read float $rounded_average
 * @property-read string $sentiment
 * @property-read string $stars
 * @property-read float $verified_percentage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberStoryRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read \App\Models\Story $story
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate excellent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate highRated(float $minRating = 4)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate mostRated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate reliable()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate trending(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereAverageRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereRatingDistribution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereSumRatings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereTotalRatings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryRatingAggregate whereUpdatedAt($value)
 */
	class StoryRatingAggregate extends \Eloquent {}
}

namespace App\Models{
/**
 * StoryView Model for Daily Stories App with Filament Integration
 * 
 * Enhanced analytics and tracking system for story views with comprehensive
 * insights, performance metrics, and user behavior analysis.
 *
 * @property int $id
 * @property int $story_id
 * @property string|null $device_id
 * @property int|null $member_id
 * @property string|null $session_id
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $referrer
 * @property array|null $metadata
 * @property Carbon $viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read array $browser_info
 * @property-read bool $is_mobile
 * @property-read string $time_ago
 * @property-read string $viewer_name
 * @property-read string $viewer_type
 * @property-read \App\Models\Member|null $member
 * @property-read \App\Models\Story $story
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView anonymous()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView byMember(int $memberId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView byStory(int $storyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView desktop()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView guests()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView members()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView mobile()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView thisMonth()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView thisWeek()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView today()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView unique()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereReferrer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereStoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoryView whereViewedAt($value)
 */
	class StoryView extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Story> $stories
 * @property-read int|null $stories_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereUpdatedAt($value)
 */
	class Tag extends \Eloquent {}
}

namespace App\Models{
/**
 * User Model for Admin Panel Access
 * 
 * This model is specifically for admin panel users who manage the system.
 * Customer/member avatars are handled by the Member model.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $display_name
 * @property-read string $initials
 * @property-read array $role_names
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User admins()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User superAdmins()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

