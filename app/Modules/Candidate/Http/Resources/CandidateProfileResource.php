<?php

namespace App\Modules\Candidate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'headline' => $this->headline,
            'base_summary' => $this->base_summary,
            'years_experience' => $this->years_experience,
            'preferred_roles' => $this->preferred_roles ?? [],
            'preferred_locations' => $this->preferred_locations ?? [],
            'preferred_job_types' => $this->preferred_job_types ?? [],
            'core_skills' => $this->core_skills ?? [],
            'nice_to_have_skills' => $this->nice_to_have_skills ?? [],
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'portfolio_url' => $this->portfolio_url,
            'experiences' => $this->whenLoaded('experiences', fn () => $this->experiences->map(fn ($experience) => [
                'id' => $experience->id,
                'company' => $experience->company,
                'title' => $experience->title,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'description' => $experience->description,
            ])->values()->all(), []),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'skills' => $project->tech_stack ?? [],
            ])->values()->all(), []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
