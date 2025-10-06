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
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'question',
        'answer',
        'order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Default values for attributes
     */
    protected $attributes = [
        'order' => 0,
        'is_active' => true,
    ];

    /**
     * Scope: Get only active FAQs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Get active FAQs in display order (for API)
     */
    public static function getActiveFAQs()
    {
        return self::active()
            ->ordered()
            ->get(['id', 'question', 'answer', 'order']);
    }
}
