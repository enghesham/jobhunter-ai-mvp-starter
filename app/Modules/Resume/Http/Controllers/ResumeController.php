<?php

namespace App\Modules\Resume\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Resume\Domain\Models\TailoredResume;
use App\Modules\Resume\Http\Requests\GenerateResumeRequest;
use App\Modules\Resume\Http\Resources\TailoredResumeResource;
use App\Services\Resume\ResumeGenerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class ResumeController extends Controller
{
    public function index(): JsonResponse
    {
        $resumes = TailoredResume::query()
            ->where('user_id', auth()->id())
            ->with(['job', 'profile'])
            ->latest()
            ->paginate();

        return ApiResponse::success(TailoredResumeResource::collection($resumes)->response()->getData(true));
    }

    public function show(TailoredResume $resume): JsonResponse
    {
        abort_if($resume->user_id !== auth()->id(), 404);

        return ApiResponse::success(new TailoredResumeResource($resume->load(['job', 'profile'])));
    }

    public function generate(GenerateResumeRequest $request, Job $job, ResumeGenerationService $resumeGenerationService): JsonResponse
    {
        $this->authorize('view', $job);
        try {
            $profileId = (int) ($request->validated()['profile_id'] ?? 1);
            $versionName = (string) ($request->validated()['version_name'] ?? 'v1');
            $force = (bool) ($request->validated()['force'] ?? false);
            $profile = CandidateProfile::with(['experiences', 'projects'])->find($profileId);

            if (! $profile) {
                return ApiResponse::error('Candidate profile not found.', 404);
            }

            $this->authorize('view', $profile);

            $resume = $resumeGenerationService->generate($job, $profile, $versionName, $force);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new TailoredResumeResource($resume->load(['job', 'profile'])), 201);
    }
}
