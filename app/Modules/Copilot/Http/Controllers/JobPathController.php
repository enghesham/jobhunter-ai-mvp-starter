<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Copilot\Http\Requests\UpsertJobPathRequest;
use App\Modules\Copilot\Http\Resources\JobPathResource;
use App\Services\Copilot\JobPathService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPathController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paths = JobPath::query()
            ->where('user_id', $request->user()->id)
            ->with('careerProfile')
            ->latest()
            ->paginate();

        return ApiResponse::success(JobPathResource::collection($paths)->response()->getData(true));
    }

    public function store(UpsertJobPathRequest $request, JobPathService $jobPathService): JsonResponse
    {
        $path = $jobPathService->create($request->user(), $request->validated());

        return ApiResponse::success(new JobPathResource($path), 201);
    }

    public function show(JobPath $jobPath): JsonResponse
    {
        $this->authorize('view', $jobPath);

        return ApiResponse::success(new JobPathResource($jobPath->load('careerProfile')));
    }

    public function update(UpsertJobPathRequest $request, JobPath $jobPath, JobPathService $jobPathService): JsonResponse
    {
        $this->authorize('update', $jobPath);

        $path = $jobPathService->update($jobPath, $request->validated());

        return ApiResponse::success(new JobPathResource($path));
    }

    public function destroy(JobPath $jobPath): JsonResponse
    {
        $this->authorize('delete', $jobPath);

        $jobPath->delete();

        return ApiResponse::success(['message' => 'Job path deleted']);
    }
}
