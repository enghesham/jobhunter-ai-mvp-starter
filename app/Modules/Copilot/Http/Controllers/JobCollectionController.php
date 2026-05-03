<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\CollectJobsForPathJob;
use App\Modules\Copilot\Domain\Models\JobCollectionRun;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Copilot\Http\Resources\JobCollectionRunResource;
use App\Services\JobCollection\JobPathCollectionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobCollectionController extends Controller
{
    public function runs(Request $request): JsonResponse
    {
        $runs = JobCollectionRun::query()
            ->where('user_id', $request->user()->id)
            ->with('jobPath')
            ->when($request->integer('job_path_id'), fn ($query, int $jobPathId) => $query->where('job_path_id', $jobPathId))
            ->latest()
            ->paginate();

        return ApiResponse::success(JobCollectionRunResource::collection($runs)->response()->getData(true));
    }

    public function collectJobPath(Request $request, JobPath $jobPath, JobPathCollectionService $collector): JsonResponse
    {
        Gate::authorize('view', $jobPath);

        if (! $jobPath->is_active) {
            return ApiResponse::error('This Job Path is inactive. Activate it before collecting jobs.', 422);
        }

        if ($request->boolean('sync')) {
            return ApiResponse::success(new JobCollectionRunResource($collector->collect($jobPath)));
        }

        CollectJobsForPathJob::dispatch($jobPath->id);

        return ApiResponse::success([
            'message' => 'Job collection queued successfully.',
            'job_path_id' => $jobPath->id,
        ], 202);
    }

    public function collectDue(Request $request, JobPathCollectionService $collector): JsonResponse
    {
        $paths = $request->boolean('all_active')
            ? $collector->activePaths($request->user())
            : $collector->duePaths($request->user());

        if ($request->boolean('sync')) {
            $runs = $paths
                ->map(fn (JobPath $path) => $collector->collect($path))
                ->values();

            return ApiResponse::success([
                'queued' => 0,
                'processed' => $runs->count(),
                'runs' => JobCollectionRunResource::collection($runs),
            ]);
        }

        foreach ($paths as $path) {
            CollectJobsForPathJob::dispatch($path->id);
        }

        return ApiResponse::success([
            'queued' => $paths->count(),
            'processed' => 0,
        ], 202);
    }
}
