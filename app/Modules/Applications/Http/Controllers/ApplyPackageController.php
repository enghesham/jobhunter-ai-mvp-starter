<?php

namespace App\Modules\Applications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Applications\Domain\Models\ApplyPackage;
use App\Modules\Applications\Http\Requests\GenerateApplyPackageRequest;
use App\Modules\Applications\Http\Requests\UpdateApplyPackageRequest;
use App\Modules\Applications\Http\Resources\ApplicationResource;
use App\Modules\Applications\Http\Resources\ApplyPackageResource;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\Applications\ApplyPackageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApplyPackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = ApplyPackage::query()
            ->where('user_id', auth()->id())
            ->with(['job', 'careerProfile', 'jobPath', 'resume', 'application'])
            ->latest()
            ->paginate();

        return ApiResponse::success(ApplyPackageResource::collection($packages)->response()->getData(true));
    }

    public function storeForJob(GenerateApplyPackageRequest $request, Job $job, ApplyPackageService $service): JsonResponse
    {
        $this->authorize('view', $job);

        $package = $service->generate($job, (int) auth()->id(), $request->validated());

        return ApiResponse::success(new ApplyPackageResource($package), 201);
    }

    public function show(ApplyPackage $applyPackage): JsonResponse
    {
        abort_if((int) $applyPackage->user_id !== (int) auth()->id(), 404);

        return ApiResponse::success(new ApplyPackageResource($applyPackage->load(['job', 'careerProfile', 'jobPath', 'resume', 'application'])));
    }

    public function update(UpdateApplyPackageRequest $request, ApplyPackage $applyPackage, ApplyPackageService $service): JsonResponse
    {
        abort_if((int) $applyPackage->user_id !== (int) auth()->id(), 404);

        return ApiResponse::success(new ApplyPackageResource(
            $service->update($applyPackage, $request->validated())
        ));
    }

    public function createApplication(ApplyPackage $applyPackage, ApplyPackageService $service): JsonResponse
    {
        abort_if((int) $applyPackage->user_id !== (int) auth()->id(), 404);

        $application = $service->createApplication($applyPackage);

        return ApiResponse::success(new ApplicationResource($application), 201);
    }
}
