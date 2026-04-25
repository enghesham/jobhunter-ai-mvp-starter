<?php

namespace App\Modules\Resume\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TailoredResumeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'profile_id' => $this->profile_id,
            'version_name' => $this->version_name,
            'headline' => $this->headline_text,
            'professional_summary' => $this->summary_text,
            'selected_skills' => $this->selected_skills,
            'selected_experience_bullets' => $this->selected_experience_bullets,
            'selected_projects' => $this->selected_projects,
            'ats_keywords' => $this->ats_keywords ?? [],
            'html_path' => $this->html_path,
            'pdf_path' => $this->pdf_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
