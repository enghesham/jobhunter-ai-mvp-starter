<?php

namespace App\Modules\Candidate\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateProfile extends Model
{
    protected $fillable = [
        'full_name',
        'headline',
        'base_summary',
        'years_experience',
        'preferred_roles',
        'preferred_locations',
        'preferred_job_types',
        'core_skills',
        'nice_to_have_skills',
        'resume_master_path',
        'linkedin_url',
        'github_url',
        'portfolio_url',
    ];

    protected $casts = [
        'preferred_roles' => 'array',
        'preferred_locations' => 'array',
        'preferred_job_types' => 'array',
        'core_skills' => 'array',
        'nice_to_have_skills' => 'array',
    ];

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class, 'profile_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CandidateProject::class, 'profile_id');
    }
}
