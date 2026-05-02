<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Http\Requests\UpsertCareerProfileRequest;
use App\Modules\Copilot\Http\Resources\CareerProfileResource;
use App\Services\Copilot\CareerProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = CandidateProfile::query()
            ->where('user_id', $request->user()->id)
            ->with(['experiences', 'projects'])
            ->latest()
            ->paginate();

        return ApiResponse::success(CareerProfileResource::collection($profiles)->response()->getData(true));
    }

    public function store(UpsertCareerProfileRequest $request, CareerProfileService $careerProfileService): JsonResponse
    {
        $profile = $careerProfileService->create($request->user(), $request->validated());

        return ApiResponse::success(new CareerProfileResource($profile), 201);
    }

    public function show(CandidateProfile $careerProfile): JsonResponse
    {
        $this->authorize('view', $careerProfile);

        return ApiResponse::success(new CareerProfileResource($careerProfile->load(['experiences', 'projects'])));
    }

    public function update(
        UpsertCareerProfileRequest $request,
        CandidateProfile $careerProfile,
        CareerProfileService $careerProfileService
    ): JsonResponse {
        $this->authorize('update', $careerProfile);

        $profile = $careerProfileService->update($careerProfile, $request->validated());

        return ApiResponse::success(new CareerProfileResource($profile));
    }

    public function destroy(CandidateProfile $careerProfile): JsonResponse
    {
        $this->authorize('delete', $careerProfile);

        $careerProfile->delete();

        return ApiResponse::success(['message' => 'Career profile deleted']);
    }

    public function makePrimary(CandidateProfile $careerProfile, CareerProfileService $careerProfileService): JsonResponse
    {
        $this->authorize('update', $careerProfile);

        $profile = $careerProfileService->makePrimary($careerProfile);

        return ApiResponse::success(new CareerProfileResource($profile));
    }
}
