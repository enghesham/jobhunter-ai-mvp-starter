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
            'overall_score' => $this->overall_score,
            'title_score' => $this->title_score,
            'skill_score' => $this->skill_score,
            'seniority_score' => $this->seniority_score,
            'location_score' => $this->location_score,
            'backend_focus_score' => $this->backend_focus_score,
            'domain_score' => $this->domain_score,
            'recommendation' => $this->recommendation,
            'notes' => $this->notes,
            'matched_at' => $this->matched_at?->toISOString(),
        ];
    }
}
