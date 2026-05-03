<?php

namespace App\Modules\Copilot\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCollectionRun extends Model
{
    protected $fillable = [
        'user_id',
        'job_path_id',
        'status',
        'started_at',
        'finished_at',
        'source_count',
        'fetched_count',
        'accepted_count',
        'created_count',
        'updated_count',
        'duplicate_count',
        'filtered_count',
        'failed_count',
        'opportunities_created',
        'opportunities_updated',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobPath(): BelongsTo
    {
        return $this->belongsTo(JobPath::class);
    }
}
