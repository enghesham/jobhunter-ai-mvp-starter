<?php

namespace App\Modules\Jobs\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAnalysis extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'required_skills',
        'preferred_skills',
        'must_have_skills',
        'nice_to_have_skills',
        'seniority',
        'role_type',
        'years_experience_min',
        'years_experience_max',
        'workplace_type',
        'salary_text',
        'salary_min',
        'salary_max',
        'salary_currency',
        'location_hint',
        'timezone_hint',
        'domain_tags',
        'tech_stack',
        'skill_categories',
        'responsibilities',
        'company_context',
        'ai_summary',
        'confidence_score',
        'ai_provider',
        'ai_model',
        'ai_generated_at',
        'ai_confidence_score',
        'ai_raw_response',
        'prompt_version',
        'input_hash',
        'ai_duration_ms',
        'fallback_used',
        'analyzed_at',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'preferred_skills' => 'array',
        'must_have_skills' => 'array',
        'nice_to_have_skills' => 'array',
        'domain_tags' => 'array',
        'tech_stack' => 'array',
        'skill_categories' => 'array',
        'responsibilities' => 'array',
        'ai_generated_at' => 'datetime',
        'fallback_used' => 'boolean',
        'analyzed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }
}
