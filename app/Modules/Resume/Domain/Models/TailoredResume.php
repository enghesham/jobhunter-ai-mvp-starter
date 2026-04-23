<?php

namespace App\Modules\Resume\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TailoredResume extends Model
{
    protected $fillable = [
        'job_id',
        'profile_id',
        'version_name',
        'summary_text',
        'skills_text',
        'experience_text',
        'ats_keywords',
        'html_path',
        'pdf_path',
    ];

    protected $casts = [
        'ats_keywords' => 'array',
    ];
}
