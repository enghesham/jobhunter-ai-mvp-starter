<?php

namespace App\Modules\Copilot\Domain\Models;

use App\Models\User;
use App\Modules\Applications\Domain\Models\ApplyPackage;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOpportunity extends Model
{
    protected $fillable = [
        'user_id',
        'job_id',
        'job_path_id',
        'career_profile_id',
        'match_id',
        'context_key',
        'quick_relevance_score',
        'match_score',
        'status',
        'recommendation',
        'reasons',
        'matched_keywords',
        'missing_keywords',
        'hidden_at',
        'hidden_reason',
        'evaluated_at',
    ];

    protected $casts = [
        'reasons' => 'array',
        'matched_keywords' => 'array',
        'missing_keywords' => 'array',
        'hidden_at' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function jobPath(): BelongsTo
    {
        return $this->belongsTo(JobPath::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'career_profile_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(JobMatch::class, 'match_id');
    }

    public function applyPackages(): HasMany
    {
        return $this->hasMany(ApplyPackage::class, 'job_id', 'job_id');
    }
}
