<?php

namespace App\Modules\Applications\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'profile_id',
        'tailored_resume_id',
        'status',
        'applied_at',
        'follow_up_date',
        'notes',
        'company_response',
        'interview_date',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'follow_up_date' => 'date',
        'interview_date' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tailoredResume(): BelongsTo
    {
        return $this->belongsTo(TailoredResume::class, 'tailored_resume_id');
    }
}
