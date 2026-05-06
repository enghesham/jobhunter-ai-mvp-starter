<?php

namespace App\Modules\Copilot\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOpportunityPreference extends Model
{
    protected $fillable = [
        'user_id',
        'default_min_relevance_score',
        'default_min_match_score',
        'quick_recommended_score',
        'store_below_threshold',
        'show_below_threshold',
        'metadata',
    ];

    protected $casts = [
        'default_min_relevance_score' => 'integer',
        'default_min_match_score' => 'integer',
        'quick_recommended_score' => 'integer',
        'store_below_threshold' => 'boolean',
        'show_below_threshold' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
