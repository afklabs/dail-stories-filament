<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PushNotification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_value',
        'data',
        'status',
        'scheduled_at',
        'sent_at',
        'success_count',
        'failure_count',
        'created_by',
        'sent_by',
        'error_message',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    /**
     * Valid target types
     */
    public const TARGET_ALL = 'all';
    public const TARGET_TOPIC = 'topic';
    public const TARGET_TOKENS = 'tokens';

    /**
     * Valid status values
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the user who created this notification
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who sent this notification
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Scope: Only draft notifications
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope: Only scheduled notifications
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope: Only sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope: Only failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: Ready to send (scheduled time has passed)
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Check if notification is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if notification is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Check if notification is sent
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if notification failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get formatted status badge
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_DRAFT => ['label' => 'Draft', 'color' => 'gray'],
            self::STATUS_SCHEDULED => ['label' => 'Scheduled', 'color' => 'warning'],
            self::STATUS_SENDING => ['label' => 'Sending', 'color' => 'info'],
            self::STATUS_SENT => ['label' => 'Sent', 'color' => 'success'],
            self::STATUS_FAILED => ['label' => 'Failed', 'color' => 'danger'],
            default => ['label' => 'Unknown', 'color' => 'gray'],
        };
    }

    /**
     * Get formatted target type
     */
    public function getTargetTypeLabel(): string
    {
        return match ($this->target_type) {
            self::TARGET_ALL => 'All Users',
            self::TARGET_TOPIC => 'Topic: ' . ($this->target_value ?? 'Unknown'),
            self::TARGET_TOKENS => 'Specific Devices (' . count(explode(',', $this->target_value ?? '')) . ')',
            default => 'Unknown',
        };
    }

    /**
     * Get delivery success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->success_count + $this->failure_count;

        if ($total === 0) {
            return 0.0;
        }

        return round(($this->success_count / $total) * 100, 1);
    }

    /**
     * Get total recipients
     */
    public function getTotalRecipientsAttribute(): int
    {
        return $this->success_count + $this->failure_count;
    }

    /**
     * Check if notification is ready to send now
     */
    public function isReadyToSend(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at
            && $this->scheduled_at <= now();
    }

    /**
     * Mark notification as sending
     */
    public function markAsSending(): void
    {
        $this->update(['status' => self::STATUS_SENDING]);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(int $successCount, int $failureCount): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get human-readable time until send
     */
    public function getTimeUntilSendAttribute(): ?string
    {
        if (!$this->scheduled_at || $this->isSent()) {
            return null;
        }

        if ($this->scheduled_at <= now()) {
            return 'Ready to send';
        }

        return $this->scheduled_at->diffForHumans();
    }
}
