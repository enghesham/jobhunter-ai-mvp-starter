<?php

namespace App\Modules\Matching\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'profile_id',
        'overall_score',
        'title_score',
        'skill_score',
        'experience_score',
        'seniority_score',
        'location_score',
        'backend_focus_score',
        'domain_score',
        'notes',
        'why_matched',
        'missing_skills',
        'missing_required_skills',
        'nice_to_have_gaps',
        'strength_areas',
        'risk_flags',
        'resume_focus_points',
        'ai_recommendation_summary',
        'recommendation',
        'recommendation_action',
        'ai_provider',
        'ai_model',
        'ai_generated_at',
        'ai_confidence_score',
        'ai_raw_response',
        'prompt_version',
        'input_hash',
        'ai_duration_ms',
        'fallback_used',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'ai_generated_at' => 'datetime',
        'fallback_used' => 'boolean',
        'missing_skills' => 'array',
        'missing_required_skills' => 'array',
        'nice_to_have_gaps' => 'array',
        'strength_areas' => 'array',
        'risk_flags' => 'array',
        'resume_focus_points' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
