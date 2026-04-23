<?php

namespace App\Modules\Candidate\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateExperience extends Model
{
    protected $fillable = [
        'profile_id',
        'company',
        'title',
        'start_date',
        'end_date',
        'description',
        'achievements',
        'tech_stack',
    ];

    protected $casts = [
        'achievements' => 'array',
        'tech_stack' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }
}
