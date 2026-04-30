<?php

namespace App\Modules\Jobs\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Modules\Matching\Domain\Models\JobMatch;

class Job extends Model
{
    protected $fillable = [
        'external_id',
        'user_id',
        'source_id',
        'company_name',
        'title',
        'location',
        'is_remote',
        'remote_type',
        'employment_type',
        'description_raw',
        'description_clean',
        'apply_url',
        'raw_payload',
        'salary_text',
        'posted_at',
        'hash',
        'job_fingerprint',
        'source_hash',
        'status',
    ];

    protected $casts = [
        'is_remote' => 'boolean',
        'raw_payload' => 'array',
        'posted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(JobAnalysis::class, 'job_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class, 'job_id');
    }
}
