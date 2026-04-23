<?php

namespace App\Modules\Candidate\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProject extends Model
{
    protected $fillable = [
        'profile_id',
        'name',
        'description',
        'tech_stack',
        'url',
    ];

    protected $casts = [
        'tech_stack' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'profile_id');
    }
}
