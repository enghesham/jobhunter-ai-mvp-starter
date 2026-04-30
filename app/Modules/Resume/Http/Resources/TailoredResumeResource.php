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
            'candidate_profile_id' => $this->profile_id,
            'version_name' => $this->version_name,
            'headline' => $this->headline_text,
            'tailored_headline' => $this->headline_text,
            'professional_summary' => $this->summary_text,
            'tailored_summary' => $this->summary_text,
            'selected_skills' => $this->selected_skills,
            'selected_experience_bullets' => $this->selected_experience_bullets,
            'tailored_experience_bullets' => $this->selected_experience_bullets,
            'selected_projects' => $this->selected_projects,
            'ats_keywords' => $this->ats_keywords ?? [],
            'warnings_or_gaps' => $this->warnings_or_gaps ?? [],
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->ai_generated_at?->toISOString(),
            'ai_confidence_score' => $this->ai_confidence_score,
            'prompt_version' => $this->prompt_version,
            'ai_duration_ms' => $this->ai_duration_ms,
            'fallback_used' => (bool) $this->fallback_used,
            'html_path' => $this->html_path,
            'pdf_path' => $this->pdf_path,
            'html_url' => $this->html_path ? url('/storage/'.$this->html_path) : null,
            'pdf_url' => $this->pdf_path ? url('/storage/'.$this->pdf_path) : null,
            'download_pdf_url' => route('jobhunter.resumes.download-pdf', ['resume' => $this->id]),
            'job' => $this->whenLoaded('job', fn () => [
                'id' => $this->job?->id,
                'title' => $this->job?->title,
                'company_name' => $this->job?->company_name,
                'url' => $this->job?->apply_url,
            ]),
            'candidate_profile' => $this->whenLoaded('profile', fn () => [
                'id' => $this->profile?->id,
                'full_name' => $this->profile?->full_name,
                'headline' => $this->profile?->headline,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
