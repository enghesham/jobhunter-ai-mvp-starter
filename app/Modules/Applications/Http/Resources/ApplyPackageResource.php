<?php

namespace App\Modules\Applications\Http\Resources;

use App\Modules\Copilot\Http\Resources\JobPathResource;
use App\Modules\Jobs\Http\Resources\JobResource;
use App\Modules\Resume\Http\Resources\TailoredResumeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplyPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'career_profile_id' => $this->career_profile_id,
            'candidate_profile_id' => $this->career_profile_id,
            'job_path_id' => $this->job_path_id,
            'application_id' => $this->application_id,
            'resume_id' => $this->resume_id,
            'cover_letter' => $this->cover_letter,
            'application_answers' => $this->application_answers ?? [],
            'salary_answer' => $this->salary_answer,
            'notice_period_answer' => $this->notice_period_answer,
            'interest_answer' => $this->interest_answer,
            'strengths' => $this->strengths ?? [],
            'gaps' => $this->gaps ?? [],
            'interview_questions' => $this->interview_questions ?? [],
            'follow_up_email' => $this->follow_up_email,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->ai_generated_at?->toISOString(),
            'ai_confidence_score' => $this->ai_confidence_score,
            'ai_duration_ms' => $this->ai_duration_ms,
            'prompt_version' => $this->prompt_version,
            'fallback_used' => (bool) $this->fallback_used,
            'status' => $this->status,
            'metadata' => $this->metadata ?? [],
            'job' => new JobResource($this->whenLoaded('job')),
            'job_path' => new JobPathResource($this->whenLoaded('jobPath')),
            'career_profile' => $this->whenLoaded('careerProfile', fn () => [
                'id' => $this->careerProfile?->id,
                'full_name' => $this->careerProfile?->full_name,
                'headline' => $this->careerProfile?->headline,
            ]),
            'resume' => new TailoredResumeResource($this->whenLoaded('resume')),
            'application' => new ApplicationResource($this->whenLoaded('application')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
