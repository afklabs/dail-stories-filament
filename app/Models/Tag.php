<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * @return BelongsToMany<Story>
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_tags');
    }
}