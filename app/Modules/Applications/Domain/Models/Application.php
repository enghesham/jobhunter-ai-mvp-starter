<?php

namespace App\Modules\Applications\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $fillable = [
        'job_id',
        'profile_id',
        'tailored_resume_id',
        'status',
        'applied_at',
        'follow_up_date',
        'notes',
        'company_response',
        'interview_date',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'follow_up_date' => 'date',
        'interview_date' => 'datetime',
    ];
}
