<?php

namespace App\Modules\Resume\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TailoredResume extends Model
{
    protected $fillable = [
        'job_id',
        'profile_id',
        'version_name',
        'headline_text',
        'summary_text',
        'skills_text',
        'experience_text',
        'projects_text',
        'ats_keywords',
        'html_path',
        'pdf_path',
    ];

    protected $casts = [
        'ats_keywords' => 'array',
    ];

    public function getSelectedSkillsAttribute(): array
    {
        return $this->splitLines($this->skills_text);
    }

    public function getSelectedExperienceBulletsAttribute(): array
    {
        return $this->splitLines($this->experience_text);
    }

    public function getSelectedProjectsAttribute(): array
    {
        return $this->splitLines($this->projects_text);
    }

    private function splitLines(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }
}
