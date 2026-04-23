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
        'seniority',
        'role_type',
        'domain_tags',
        'ai_summary',
        'analyzed_at',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'preferred_skills' => 'array',
        'domain_tags' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }
}
