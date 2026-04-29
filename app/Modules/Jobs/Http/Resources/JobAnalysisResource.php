<?php

namespace App\Modules\Jobs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'required_skills' => $this->required_skills ?? [],
            'preferred_skills' => $this->preferred_skills ?? [],
            'must_have_skills' => $this->must_have_skills ?? [],
            'nice_to_have_skills' => $this->nice_to_have_skills ?? [],
            'seniority' => $this->seniority,
            'role_type' => $this->role_type,
            'domain_tags' => $this->domain_tags ?? [],
            'tech_stack' => $this->tech_stack ?? [],
            'responsibilities' => $this->responsibilities ?? [],
            'company_context' => $this->company_context,
            'ai_summary' => $this->ai_summary,
            'confidence_score' => $this->confidence_score,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->ai_generated_at?->toISOString(),
            'ai_confidence_score' => $this->ai_confidence_score,
            'prompt_version' => $this->prompt_version,
            'ai_duration_ms' => $this->ai_duration_ms,
            'fallback_used' => (bool) $this->fallback_used,
            'analyzed_at' => $this->analyzed_at?->toISOString(),
        ];
    }
}
