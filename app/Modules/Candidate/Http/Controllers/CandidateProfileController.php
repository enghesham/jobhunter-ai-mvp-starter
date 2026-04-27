<?php

namespace App\Modules\Candidate\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Candidate\Http\Requests\UpsertCandidateProfileRequest;
use App\Modules\Candidate\Http\Resources\CandidateProfileResource;
use App\Services\Candidate\CandidateProfilePersistenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CandidateProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = CandidateProfile::query()
            ->where('user_id', auth()->id())
            ->with(['experiences', 'projects'])
            ->latest()
            ->paginate();

        return ApiResponse::success(CandidateProfileResource::collection($profiles)->response()->getData(true));
    }

    public function store(UpsertCandidateProfileRequest $request, CandidateProfilePersistenceService $persistenceService): JsonResponse
    {
        $profile = $persistenceService->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return ApiResponse::success(new CandidateProfileResource($profile), 201);
    }

    public function show(CandidateProfile $candidateProfile): JsonResponse
    {
        $this->authorize('view', $candidateProfile);
        return ApiResponse::success(new CandidateProfileResource($candidateProfile->load(['experiences', 'projects'])));
    }

    public function update(UpsertCandidateProfileRequest $request, CandidateProfile $candidateProfile, CandidateProfilePersistenceService $persistenceService): JsonResponse
    {
        $this->authorize('update', $candidateProfile);
        $profile = $persistenceService->update($candidateProfile, $request->validated());

        return ApiResponse::success(new CandidateProfileResource($profile));
    }

    public function destroy(CandidateProfile $candidateProfile): JsonResponse
    {
        $this->authorize('delete', $candidateProfile);
        $candidateProfile->delete();

        return ApiResponse::success(['message' => 'Candidate profile deleted']);
    }

    public function import(UpsertCandidateProfileRequest $request, CandidateProfilePersistenceService $persistenceService): JsonResponse
    {
        $profile = $persistenceService->import((int) auth()->id(), $request->validated());

        return ApiResponse::success(new CandidateProfileResource($profile));
    }
}
