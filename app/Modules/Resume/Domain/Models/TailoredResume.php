<?php

namespace App\Modules\Resume\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TailoredResume extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'profile_id',
        'version_name',
        'headline_text',
        'summary_text',
        'skills_text',
        'experience_text',
        'projects_text',
        'ats_keywords',
        'html_path',
        'pdf_path',
    ];

    protected $casts = [
        'ats_keywords' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }

    public function getSelectedSkillsAttribute(): array
    {
        return $this->splitLines($this->skills_text);
    }

    public function getSelectedExperienceBulletsAttribute(): array
    {
        return $this->splitLines($this->experience_text);
    }

    public function getSelectedProjectsAttribute(): array
    {
        return $this->splitLines($this->projects_text);
    }

    private function splitLines(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }
}
