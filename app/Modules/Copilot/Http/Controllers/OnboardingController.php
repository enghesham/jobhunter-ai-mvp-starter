<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Copilot\Http\Requests\SuggestJobPathsRequest;
use App\Modules\Copilot\Http\Requests\UpsertCareerProfileRequest;
use App\Modules\Copilot\Http\Resources\CareerProfileResource;
use App\Modules\Copilot\Http\Resources\OnboardingStateResource;
use App\Services\Copilot\OnboardingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingService $onboardingService): JsonResponse
    {
        $state = $onboardingService->state($request->user());
        $profile = $request->user()
            ->candidateProfiles()
            ->with(['experiences', 'projects'])
            ->orderByDesc('is_primary')
            ->latest()
            ->first();

        return ApiResponse::success([
            'state' => new OnboardingStateResource($state),
            'career_profile' => $profile ? new CareerProfileResource($profile) : null,
            'understanding' => $profile ? $onboardingService->summarizeProfile($profile) : null,
            'best_matches_path' => '/matches',
        ]);
    }

    public function careerProfile(UpsertCareerProfileRequest $request, OnboardingService $onboardingService): JsonResponse
    {
        $result = $onboardingService->saveCareerProfile($request->user(), $request->validated());

        return ApiResponse::success([
            'state' => new OnboardingStateResource($result['state']),
            'career_profile' => new CareerProfileResource($result['career_profile']),
            'understanding' => $result['understanding'],
        ], 201);
    }

    public function suggestJobPaths(SuggestJobPathsRequest $request, OnboardingService $onboardingService): JsonResponse
    {
        $result = $onboardingService->suggestJobPaths(
            $request->user(),
            $request->validated('career_profile_id'),
        );

        return ApiResponse::success([
            'state' => new OnboardingStateResource($result['state']),
            'career_profile' => new CareerProfileResource($result['career_profile']),
            'suggestions' => $result['suggestions'],
        ]);
    }

    public function complete(Request $request, OnboardingService $onboardingService): JsonResponse
    {
        $state = $onboardingService->complete($request->user());

        return ApiResponse::success([
            'state' => new OnboardingStateResource($state),
            'best_matches_path' => '/matches',
        ]);
    }
}
