<?php

namespace App\Modules\Copilot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'current_step' => $this->current_step,
            'is_completed' => $this->completed_at !== null,
            'completed_at' => $this->completed_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'next_action' => $this->nextAction(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function nextAction(): string
    {
        return match ($this->current_step) {
            'career_profile' => 'Create or import your career profile.',
            'review_profile' => 'Review the profile summary and confirm it is accurate.',
            'suggest_job_paths' => 'Review suggested job paths and choose the ones you want.',
            'preferences' => 'Confirm preferences such as notifications and auto collect.',
            'done' => 'Open Best Matches.',
            default => 'Continue onboarding.',
        };
    }
}
