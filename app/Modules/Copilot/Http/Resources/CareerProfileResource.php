<?php

namespace App\Modules\Copilot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->full_name,
            'headline' => $this->headline,
            'summary' => $this->base_summary,
            'years_experience' => $this->years_experience,
            'skills' => [
                'primary' => $this->core_skills ?? [],
                'secondary' => $this->nice_to_have_skills ?? [],
            ],
            'work_preferences' => [
                'roles' => $this->preferred_roles ?? [],
                'locations' => $this->preferred_locations ?? [],
                'job_types' => $this->preferred_job_types ?? [],
            ],
            'links' => [
                'linkedin' => $this->linkedin_url,
                'github' => $this->github_url,
                'portfolio' => $this->portfolio_url,
            ],
            'experience' => $this->whenLoaded('experiences', fn () => $this->experiences->map(fn ($experience) => [
                'company' => $experience->company,
                'title' => $experience->title,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'summary' => $experience->description,
            ])->values(), []),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'name' => $project->name,
                'summary' => $project->description,
                'skills' => $project->tech_stack ?? [],
                'url' => $project->url,
            ])->values(), []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
