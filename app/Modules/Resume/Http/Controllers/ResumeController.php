<?php

namespace App\Modules\Resume\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Resume\Http\Requests\GenerateResumeRequest;
use App\Modules\Resume\Http\Resources\TailoredResumeResource;
use App\Services\Resume\ResumeGenerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class ResumeController extends Controller
{
    public function generate(GenerateResumeRequest $request, Job $job, ResumeGenerationService $resumeGenerationService): JsonResponse
    {
        $this->authorize('view', $job);
        try {
            $profileId = (int) ($request->validated()['profile_id'] ?? 1);
            $versionName = (string) ($request->validated()['version_name'] ?? 'v1');
            $profile = CandidateProfile::with(['experiences', 'projects'])->find($profileId);

            if (! $profile) {
                return ApiResponse::error('Candidate profile not found.', 404);
            }

            $this->authorize('view', $profile);

            $resume = $resumeGenerationService->generate($job, $profile, $versionName);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new TailoredResumeResource($resume), 201);
    }
}
