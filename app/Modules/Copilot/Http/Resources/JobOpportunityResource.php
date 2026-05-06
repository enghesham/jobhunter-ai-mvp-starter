<?php

namespace App\Modules\Copilot\Http\Resources;

use App\Modules\Jobs\Http\Resources\JobMatchResource;
use App\Modules\Jobs\Http\Resources\JobResource;
use App\Services\Copilot\OpportunityPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $applyPackage = $this->applyPackageForOpportunity();
        $preferences = app(OpportunityPreferenceService::class);
        $user = $request->user();
        $thresholds = $user ? [
            'min_relevance_score' => $preferences->minRelevanceScore($user, $this->jobPath),
            'min_match_score' => $preferences->minMatchScore($user, $this->jobPath),
            'quick_recommended_score' => $preferences->quickRecommendedScore($user, $this->jobPath),
        ] : null;

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job_path_id' => $this->job_path_id,
            'career_profile_id' => $this->career_profile_id,
            'match_id' => $this->match_id,
            'context_key' => $this->context_key,
            'quick_relevance_score' => $this->quick_relevance_score,
            'match_score' => $this->match_score,
            'display_score' => $this->match_score ?? $this->quick_relevance_score,
            'is_evaluated' => $this->match_id !== null || $this->evaluated_at !== null,
            'status' => $this->status,
            'recommendation' => $this->recommendation,
            'reasons' => $this->reasons ?? [],
            'matched_keywords' => $this->matched_keywords ?? [],
            'missing_keywords' => $this->missing_keywords ?? [],
            'thresholds' => $thresholds,
            'hidden_at' => $this->hidden_at?->toISOString(),
            'hidden_reason' => $this->hidden_reason,
            'evaluated_at' => $this->evaluated_at?->toISOString(),
            'job' => new JobResource($this->whenLoaded('job')),
            'job_path' => $this->whenLoaded('jobPath', fn () => [
                'id' => $this->jobPath?->id,
                'name' => $this->jobPath?->name,
                'min_relevance_score' => $this->jobPath?->min_relevance_score,
                'min_match_score' => $this->jobPath?->min_match_score,
            ]),
            'career_profile' => $this->whenLoaded('careerProfile', fn () => [
                'id' => $this->careerProfile?->id,
                'full_name' => $this->careerProfile?->full_name,
                'headline' => $this->careerProfile?->headline,
                'core_skills' => $this->careerProfile?->core_skills ?? [],
                'nice_to_have_skills' => $this->careerProfile?->nice_to_have_skills ?? [],
            ]),
            'match' => new JobMatchResource($this->whenLoaded('match')),
            'apply_package_id' => $applyPackage?->id,
            'apply_package' => $applyPackage ? [
                'id' => $applyPackage->id,
                'status' => $applyPackage->status,
                'application_id' => $applyPackage->application_id,
                'resume_id' => $applyPackage->resume_id,
                'created_at' => $applyPackage->created_at?->toISOString(),
                'updated_at' => $applyPackage->updated_at?->toISOString(),
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function applyPackageForOpportunity(): mixed
    {
        if (! $this->relationLoaded('applyPackages')) {
            return null;
        }

        return $this->applyPackages->first(function ($package): bool {
            return (int) $package->career_profile_id === (int) $this->career_profile_id
                && (int) ($package->job_path_id ?? 0) === (int) ($this->job_path_id ?? 0);
        });
    }
}
