<?php

namespace App\Modules\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeJobJob;
use App\Jobs\MatchJobToProfileJob;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Http\Requests\MatchJobRequest;
use App\Modules\Jobs\Http\Resources\JobResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class JobController extends Controller
{
    public function index(): JsonResponse
    {
        $jobs = Job::query()
            ->where('user_id', auth()->id())
            ->with(['analysis', 'source'])
            ->latest('posted_at')
            ->paginate();

        return ApiResponse::success(JobResource::collection($jobs)->response()->getData(true));
    }

    public function show(Job $job): JsonResponse
    {
        $this->authorize('view', $job);
        return ApiResponse::success(new JobResource($job->load('analysis', 'source', 'matches')));
    }

    public function analyze(Job $job): JsonResponse
    {
        $this->authorize('view', $job);
        try {
            AnalyzeJobJob::dispatchSync($job->id);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new JobResource($job->fresh(['analysis', 'source'])));
    }

    public function match(MatchJobRequest $request, Job $job): JsonResponse
    {
        $this->authorize('view', $job);
        try {
            $validated = $request->validated();
            $profileId = (int) ($validated['profile_id'] ?? 1);
            MatchJobToProfileJob::dispatchSync($job->id, $profileId);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new JobResource($job->fresh(['analysis', 'source', 'matches'])));
    }
}
