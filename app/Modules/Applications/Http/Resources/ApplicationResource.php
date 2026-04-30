<?php

namespace App\Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'profile_id' => $this->profile_id,
            'candidate_profile_id' => $this->profile_id,
            'tailored_resume_id' => $this->tailored_resume_id,
            'resume_id' => $this->tailored_resume_id,
            'status' => $this->status,
            'applied_at' => $this->applied_at?->toISOString(),
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'notes' => $this->notes,
            'company_response' => $this->company_response,
            'interview_date' => $this->interview_date?->toISOString(),
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
            'resume' => $this->whenLoaded('tailoredResume', fn () => [
                'id' => $this->tailoredResume?->id,
                'headline' => $this->tailoredResume?->headline_text,
                'html_path' => $this->tailoredResume?->html_path,
                'pdf_path' => $this->tailoredResume?->pdf_path,
            ]),
            'events' => ApplicationEventResource::collection($this->whenLoaded('events')),
            'materials' => ApplicationMaterialResource::collection($this->whenLoaded('materials')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
