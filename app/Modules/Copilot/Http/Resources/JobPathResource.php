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
            'name' => $this->name,
            'description' => $this->description,
            'target_roles' => $this->target_roles ?? [],
            'target_domains' => $this->target_domains ?? [],
            'include_keywords' => $this->include_keywords ?? [],
            'exclude_keywords' => $this->exclude_keywords ?? [],
            'required_skills' => $this->required_skills ?? [],
            'optional_skills' => $this->optional_skills ?? [],
            'seniority_levels' => $this->seniority_levels ?? [],
            'preferred_locations' => $this->preferred_locations ?? [],
            'preferred_countries' => $this->preferred_countries ?? [],
            'preferred_job_types' => $this->preferred_job_types ?? [],
            'remote_preference' => $this->remote_preference,
            'min_relevance_score' => $this->min_relevance_score,
            'min_match_score' => $this->min_match_score,
            'salary_min' => $this->salary_min,
            'salary_currency' => $this->salary_currency,
            'is_active' => (bool) $this->is_active,
            'auto_collect_enabled' => (bool) $this->auto_collect_enabled,
            'notifications_enabled' => (bool) $this->notifications_enabled,
            'scan_interval_hours' => $this->scan_interval_hours,
            'last_scanned_at' => $this->last_scanned_at?->toISOString(),
            'next_scan_at' => $this->next_scan_at?->toISOString(),
            'is_due_for_scan' => $this->isDueForScan(),
            'metadata' => $this->metadata ?? [],
            'title' => $this->name,
            'goal' => $this->description,
            'target_fields' => $this->target_domains ?? [],
            'work_modes' => array_values(array_filter([$this->remote_preference])),
            'employment_types' => $this->preferred_job_types ?? [],
            'must_have_keywords' => $this->required_skills ?? [],
            'nice_to_have_keywords' => $this->optional_skills ?? [],
            'avoid_keywords' => $this->exclude_keywords ?? [],
            'min_fit_score' => $this->min_relevance_score,
            'min_apply_score' => $this->min_match_score,
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
