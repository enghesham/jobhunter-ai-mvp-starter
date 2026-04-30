<?php

namespace App\Modules\Applications\Domain\Models;

use App\Models\User;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationMaterial extends Model
{
    protected $fillable = [
        'application_id',
        'user_id',
        'job_id',
        'profile_id',
        'answer_template_id',
        'material_type',
        'key',
        'title',
        'question',
        'content_text',
        'metadata',
        'ai_provider',
        'ai_model',
        'ai_generated_at',
        'ai_confidence_score',
        'ai_raw_response',
        'prompt_version',
        'input_hash',
        'ai_duration_ms',
        'fallback_used',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ai_generated_at' => 'datetime',
        'fallback_used' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

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

    public function answerTemplate(): BelongsTo
    {
        return $this->belongsTo(AnswerTemplate::class);
    }
}
