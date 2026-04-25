<?php

namespace App\Modules\Candidate\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Candidate\Http\Requests\UpsertCandidateProfileRequest;
use App\Modules\Candidate\Http\Resources\CandidateProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CandidateProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = CandidateProfile::query()->latest()->paginate();

        return ApiResponse::success(CandidateProfileResource::collection($profiles)->response()->getData(true));
    }

    public function store(UpsertCandidateProfileRequest $request): JsonResponse
    {
        $profile = CandidateProfile::create($request->validated());

        return ApiResponse::success(new CandidateProfileResource($profile), 201);
    }

    public function show(CandidateProfile $candidateProfile): JsonResponse
    {
        return ApiResponse::success(new CandidateProfileResource($candidateProfile));
    }

    public function update(UpsertCandidateProfileRequest $request, CandidateProfile $candidateProfile): JsonResponse
    {
        $candidateProfile->update($request->validated());

        return ApiResponse::success(new CandidateProfileResource($candidateProfile->fresh()));
    }

    public function destroy(CandidateProfile $candidateProfile): JsonResponse
    {
        $candidateProfile->delete();

        return ApiResponse::success(['message' => 'Candidate profile deleted']);
    }

    public function import(UpsertCandidateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $profile = CandidateProfile::updateOrCreate(
            ['full_name' => $validated['full_name']],
            $validated
        );

        return ApiResponse::success(new CandidateProfileResource($profile->fresh()));
    }
}
