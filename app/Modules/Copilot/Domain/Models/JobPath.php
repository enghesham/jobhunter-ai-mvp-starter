<?php

namespace App\Modules\Copilot\Domain\Models;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Database\Factories\JobPathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class JobPath extends Model
{
    /** @use HasFactory<JobPathFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'career_profile_id',
        'name',
        'description',
        'target_roles',
        'target_domains',
        'include_keywords',
        'exclude_keywords',
        'required_skills',
        'optional_skills',
        'seniority_levels',
        'preferred_locations',
        'preferred_countries',
        'preferred_job_types',
        'remote_preference',
        'min_relevance_score',
        'min_match_score',
        'salary_min',
        'salary_currency',
        'is_active',
        'auto_collect_enabled',
        'notifications_enabled',
        'scan_interval_hours',
        'last_scanned_at',
        'next_scan_at',
        'metadata',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'target_domains' => 'array',
        'include_keywords' => 'array',
        'exclude_keywords' => 'array',
        'required_skills' => 'array',
        'optional_skills' => 'array',
        'seniority_levels' => 'array',
        'preferred_locations' => 'array',
        'preferred_countries' => 'array',
        'preferred_job_types' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'auto_collect_enabled' => 'boolean',
        'notifications_enabled' => 'boolean',
        'last_scanned_at' => 'datetime',
        'next_scan_at' => 'datetime',
    ];

    protected static function newFactory(): JobPathFactory
    {
        return JobPathFactory::new();
    }

    public function isDueForScan(?Carbon $now = null): bool
    {
        if (! $this->is_active || ! $this->auto_collect_enabled) {
            return false;
        }

        return $this->next_scan_at === null || $this->next_scan_at->lte($now ?? now());
    }

    public function calculateNextScanAt(?Carbon $from = null): Carbon
    {
        $hours = (int) ($this->scan_interval_hours ?: config('jobhunter.scan_hours', 6));

        return ($from ?? now())->copy()->addHours(max(1, $hours));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'career_profile_id');
    }
}
