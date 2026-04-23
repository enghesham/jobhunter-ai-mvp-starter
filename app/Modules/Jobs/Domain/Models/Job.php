<?php

namespace App\Modules\Jobs\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Modules\Matching\Domain\Models\JobMatch;

class Job extends Model
{
    protected $fillable = [
        'external_id',
        'source_id',
        'company_name',
        'title',
        'location',
        'remote_type',
        'employment_type',
        'description_raw',
        'description_clean',
        'apply_url',
        'salary_text',
        'posted_at',
        'hash',
        'status',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'source_id');
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
