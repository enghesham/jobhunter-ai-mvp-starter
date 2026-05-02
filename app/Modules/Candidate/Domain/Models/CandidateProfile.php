<?php

namespace App\Modules\Candidate\Domain\Models;

use App\Models\User;
use App\Modules\Copilot\Domain\Models\JobPath;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'user_id',
        'headline',
        'base_summary',
        'years_experience',
        'primary_role',
        'seniority_level',
        'preferred_roles',
        'preferred_locations',
        'preferred_job_types',
        'preferred_workplace_type',
        'core_skills',
        'nice_to_have_skills',
        'tools',
        'industries',
        'education',
        'certifications',
        'languages',
        'salary_expectation',
        'salary_currency',
        'raw_cv_text',
        'parsed_cv_data',
        'source',
        'is_primary',
        'metadata',
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
        'tools' => 'array',
        'industries' => 'array',
        'education' => 'array',
        'certifications' => 'array',
        'languages' => 'array',
        'parsed_cv_data' => 'array',
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'salary_expectation' => 'decimal:2',
    ];

    protected static function newFactory(): CandidateProfileFactory
    {
        return CandidateProfileFactory::new();
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CandidateProject::class, 'profile_id');
    }

    public function jobPaths(): HasMany
    {
        return $this->hasMany(JobPath::class, 'career_profile_id');
    }
}
