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
            'title' => $this->headline,
            'headline' => $this->headline,
            'professional_summary' => $this->base_summary,
            'summary' => $this->base_summary,
            'primary_role' => $this->primary_role,
            'seniority_level' => $this->seniority_level,
            'years_of_experience' => $this->years_experience,
            'years_experience' => $this->years_experience,
            'skills' => $this->core_skills ?? [],
            'secondary_skills' => $this->nice_to_have_skills ?? [],
            'tools' => $this->tools ?? [],
            'industries' => $this->industries ?? [],
            'work_preferences' => [
                'roles' => $this->preferred_roles ?? [],
                'locations' => $this->preferred_locations ?? [],
                'job_types' => $this->preferred_job_types ?? [],
                'workplace_type' => $this->preferred_workplace_type,
            ],
            'preferred_workplace_type' => $this->preferred_workplace_type,
            'preferred_locations' => $this->preferred_locations ?? [],
            'salary_expectation' => $this->salary_expectation,
            'salary_currency' => $this->salary_currency,
            'education' => $this->education ?? [],
            'certifications' => $this->certifications ?? [],
            'languages' => $this->languages ?? [],
            'raw_cv_text' => $this->raw_cv_text,
            'parsed_cv_data' => $this->parsed_cv_data ?? [],
            'source' => $this->source,
            'is_primary' => (bool) $this->is_primary,
            'metadata' => $this->metadata ?? [],
            'links' => [
                'linkedin' => $this->linkedin_url,
                'github' => $this->github_url,
                'portfolio' => $this->portfolio_url,
            ],
            'experiences' => $this->whenLoaded('experiences', fn () => $this->experiences->map(fn ($experience) => [
                'id' => $experience->id,
                'company' => $experience->company,
                'title' => $experience->title,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'description' => $experience->description,
                'achievements' => $experience->achievements ?? [],
                'skills' => $experience->tech_stack ?? [],
            ])->values()->all(), []),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'skills' => $project->tech_stack ?? [],
                'url' => $project->url,
            ])->values()->all(), []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
