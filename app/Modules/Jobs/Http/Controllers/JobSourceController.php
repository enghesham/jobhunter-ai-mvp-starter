<?php

namespace App\Modules\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ScanJobSourceJob;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Jobs\Http\Requests\ManualJobIngestionRequest;
use App\Modules\Jobs\Http\Requests\ScanJobSourceRequest;
use App\Modules\Jobs\Http\Requests\StoreJobSourceRequest;
use App\Modules\Jobs\Http\Resources\JobResource;
use App\Modules\Jobs\Http\Resources\JobSourceResource;
use App\Services\JobIngestion\JobSourceScanService;
use App\Services\JobIngestion\ManualJobIngestionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class JobSourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = JobSource::query()
            ->where('user_id', auth()->id())
            ->withCount('jobs')
            ->latest()
            ->paginate();

        return ApiResponse::success(JobSourceResource::collection($sources)->response()->getData(true));
    }

    public function store(StoreJobSourceRequest $request): JsonResponse
    {
        $source = JobSource::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return ApiResponse::success(new JobSourceResource($source), 201);
    }

    public function show(JobSource $jobSource): JsonResponse
    {
        $this->authorize('view', $jobSource);
        return ApiResponse::success(new JobSourceResource($jobSource->loadCount('jobs')));
    }

    public function update(StoreJobSourceRequest $request, JobSource $jobSource): JsonResponse
    {
        $this->authorize('update', $jobSource);
        $jobSource->update($request->validated());

        return ApiResponse::success(new JobSourceResource($jobSource->fresh()));
    }

    public function destroy(JobSource $jobSource): JsonResponse
    {
        $this->authorize('delete', $jobSource);
        $jobSource->delete();

        return ApiResponse::success(['message' => 'Job source deleted']);
    }

    public function scan(ScanJobSourceRequest $request, JobSource $jobSource, JobSourceScanService $scanner): JsonResponse
    {
        $this->authorize('view', $jobSource);
        try {
            if ($request->boolean('sync')) {
                return ApiResponse::success([
                    'job_source_id' => $jobSource->id,
                    'result' => $scanner->scan($jobSource),
                ]);
            }

            ScanJobSourceJob::dispatch($jobSource->id);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success([
            'message' => 'Scan queued successfully',
            'job_source_id' => $jobSource->id,
        ], 202);
    }

    public function ingest(ManualJobIngestionRequest $request, JobSource $jobSource, ManualJobIngestionService $ingestionService): JsonResponse
    {
        $this->authorize('update', $jobSource);
        $validated = $request->validated();
        $result = $ingestionService->ingest($jobSource, $validated['jobs']);

        return ApiResponse::success([
            'source_id' => $jobSource->id,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'jobs' => JobResource::collection(collect($result['jobs']))->resolve(),
        ], 201);
    }
}
