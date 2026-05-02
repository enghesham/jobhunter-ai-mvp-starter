<?php

namespace App\Modules\Copilot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'career_profile_id' => $this->career_profile_id,
            'title' => $this->title,
            'goal' => $this->goal,
            'target_roles' => $this->target_roles ?? [],
            'target_fields' => $this->target_fields ?? [],
            'preferred_locations' => $this->preferred_locations ?? [],
            'work_modes' => $this->work_modes ?? [],
            'employment_types' => $this->employment_types ?? [],
            'must_have_keywords' => $this->must_have_keywords ?? [],
            'nice_to_have_keywords' => $this->nice_to_have_keywords ?? [],
            'avoid_keywords' => $this->avoid_keywords ?? [],
            'min_fit_score' => $this->min_fit_score,
            'min_apply_score' => $this->min_apply_score,
            'is_active' => (bool) $this->is_active,
            'auto_collect_enabled' => (bool) $this->auto_collect_enabled,
            'scan_interval_hours' => $this->scan_interval_hours,
            'next_scan_at' => $this->next_scan_at?->toISOString(),
            'is_due_for_scan' => $this->isDueForScan(),
            'last_checked_at' => $this->last_checked_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'career_profile' => $this->whenLoaded('careerProfile', fn () => [
                'id' => $this->careerProfile?->id,
                'display_name' => $this->careerProfile?->full_name,
                'headline' => $this->careerProfile?->headline,
                'is_primary' => (bool) $this->careerProfile?->is_primary,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
