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
        'title',
        'goal',
        'target_roles',
        'target_fields',
        'preferred_locations',
        'work_modes',
        'employment_types',
        'must_have_keywords',
        'nice_to_have_keywords',
        'avoid_keywords',
        'min_fit_score',
        'min_apply_score',
        'is_active',
        'auto_collect_enabled',
        'scan_interval_hours',
        'next_scan_at',
        'metadata',
        'last_checked_at',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'target_fields' => 'array',
        'preferred_locations' => 'array',
        'work_modes' => 'array',
        'employment_types' => 'array',
        'must_have_keywords' => 'array',
        'nice_to_have_keywords' => 'array',
        'avoid_keywords' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'auto_collect_enabled' => 'boolean',
        'next_scan_at' => 'datetime',
        'last_checked_at' => 'datetime',
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
