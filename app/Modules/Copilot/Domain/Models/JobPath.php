<?php

namespace App\Modules\Copilot\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPath extends Model
{
    protected $fillable = [
        'user_id',
        'career_profile_id',
        'title',
        'goal',
        'target_roles',
        'target_fields',
        'preferred_locations',
        'work_modes',
        'employment_types',
        'must_have_keywords',
        'nice_to_have_keywords',
        'avoid_keywords',
        'min_fit_score',
        'min_apply_score',
        'is_active',
        'metadata',
        'last_checked_at',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'target_fields' => 'array',
        'preferred_locations' => 'array',
        'work_modes' => 'array',
        'employment_types' => 'array',
        'must_have_keywords' => 'array',
        'nice_to_have_keywords' => 'array',
        'avoid_keywords' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'career_profile_id');
    }
}
