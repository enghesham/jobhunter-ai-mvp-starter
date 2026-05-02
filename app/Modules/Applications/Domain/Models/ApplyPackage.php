<?php

namespace App\Modules\Applications\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplyPackage extends Model
{
    protected $fillable = [
        'user_id',
        'job_id',
        'career_profile_id',
        'job_path_id',
        'application_id',
        'resume_id',
        'cover_letter',
        'application_answers',
        'salary_answer',
        'notice_period_answer',
        'interest_answer',
        'strengths',
        'gaps',
        'interview_questions',
        'follow_up_email',
        'ai_provider',
        'ai_model',
        'ai_generated_at',
        'ai_confidence_score',
        'ai_duration_ms',
        'prompt_version',
        'input_hash',
        'fallback_used',
        'status',
        'metadata',
    ];

    protected $casts = [
        'application_answers' => 'array',
        'strengths' => 'array',
        'gaps' => 'array',
        'interview_questions' => 'array',
        'metadata' => 'array',
        'ai_generated_at' => 'datetime',
        'fallback_used' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'career_profile_id');
    }

    public function jobPath(): BelongsTo
    {
        return $this->belongsTo(JobPath::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(TailoredResume::class, 'resume_id');
    }
}
