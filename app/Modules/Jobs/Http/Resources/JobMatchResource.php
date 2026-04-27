<?php

namespace App\Modules\Jobs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'profile_id' => $this->profile_id,
            'candidate_profile_id' => $this->profile_id,
            'overall_score' => $this->overall_score,
            'title_score' => $this->title_score,
            'skill_score' => $this->skill_score,
            'seniority_score' => $this->seniority_score,
            'location_score' => $this->location_score,
            'backend_focus_score' => $this->backend_focus_score,
            'domain_score' => $this->domain_score,
            'recommendation' => $this->recommendation,
            'notes' => $this->notes,
            'why_matched' => $this->why_matched,
            'missing_skills' => $this->missing_skills ?? [],
            'strength_areas' => $this->strength_areas ?? [],
            'risk_flags' => $this->risk_flags ?? [],
            'resume_focus_points' => $this->resume_focus_points ?? [],
            'ai_recommendation_summary' => $this->ai_recommendation_summary,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->ai_generated_at?->toISOString(),
            'ai_confidence_score' => $this->ai_confidence_score,
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
            'matched_at' => $this->matched_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
