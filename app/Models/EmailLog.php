<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmailLog Model
 * 
 * Tracks all emails sent from the system
 * 
 * @property int $id
 * @property string $email_type
 * @property string $recipient_email
 * @property int|null $member_id
 * @property int|null $sent_by_user_id
 * @property string $subject
 * @property string|null $body
 * @property string $status
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $opened_at
 * @property \Carbon\Carbon|null $clicked_at
 * @property int $open_count
 * @property int $click_count
 * @property string|null $tracking_id
 * @property array|null $metadata
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_type',
        'recipient_email',
        'member_id',
        'sent_by_user_id',
        'subject',
        'body',
        'status',
        'error_message',
        'sent_at',
        'opened_at',
        'clicked_at',
        'open_count',
        'click_count',
        'tracking_id',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'open_count' => 'integer',
        'click_count' => 'integer',
        'metadata' => 'array',
    ];

    // Email Types
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_PROMOTIONAL = 'promotional';
    public const TYPE_NOTIFICATION = 'notification';

    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';

    // Email Verification
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';


    /**
     * Relationships
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark email as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Record email open
     */
    public function recordOpen(): void
    {
        $this->increment('open_count');

        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    /**
     * Record email click
     */
    public function recordClick(): void
    {
        $this->increment('click_count');

        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }
}
