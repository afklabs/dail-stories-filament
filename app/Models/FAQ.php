<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FAQ Model
 * 
 * Represents Frequently Asked Questions that admins manage
 * and members can view in the mobile app.
 */
class FAQ extends Model
{
    use HasFactory;

    /**
     * ⚠️ CRITICAL: Specify table name explicitly
     * 
     * Without this, Laravel converts "FAQ" to "f_a_q_s"
     * but our migration creates table "faqs"
     *
     * @var string
     */
    protected $table = 'faqs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Default values for attributes
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'order' => 0,
        'is_active' => true,
    ];

    /**
     * Scope: Get only active FAQs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by display order
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Get active FAQs in display order (for API)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveFAQs()
    {
        return self::active()
            ->ordered()
            ->get(['id', 'question', 'answer', 'order']);
    }
}
