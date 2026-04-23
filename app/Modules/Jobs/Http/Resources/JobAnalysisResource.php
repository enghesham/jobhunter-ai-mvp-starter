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
            'seniority' => $this->seniority,
            'role_type' => $this->role_type,
            'domain_tags' => $this->domain_tags ?? [],
            'ai_summary' => $this->ai_summary,
            'analyzed_at' => $this->analyzed_at?->toISOString(),
        ];
    }
}
