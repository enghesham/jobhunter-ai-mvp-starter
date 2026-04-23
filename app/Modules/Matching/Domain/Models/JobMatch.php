<?php

namespace App\Modules\Matching\Domain\Models;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    protected $fillable = [
        'job_id',
        'profile_id',
        'overall_score',
        'title_score',
        'skill_score',
        'seniority_score',
        'location_score',
        'backend_focus_score',
        'domain_score',
        'notes',
        'recommendation',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }
}
