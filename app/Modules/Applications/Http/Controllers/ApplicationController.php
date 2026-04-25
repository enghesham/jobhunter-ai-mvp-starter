<?php

namespace App\Modules\Applications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Http\Requests\StoreApplicationRequest;
use App\Modules\Applications\Http\Requests\UpdateApplicationRequest;
use App\Modules\Applications\Http\Resources\ApplicationResource;
use App\Services\Applications\ApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        $applications = Application::query()->latest()->paginate();

        return ApiResponse::success(ApplicationResource::collection($applications)->response()->getData(true));
    }

    public function store(StoreApplicationRequest $request, ApplicationService $applicationService): JsonResponse
    {
        $application = $applicationService->create($request->validated());

        return ApiResponse::success(new ApplicationResource($application), 201);
    }

    public function show(Application $application): JsonResponse
    {
        return ApiResponse::success(new ApplicationResource($application));
    }

    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $application->update($request->validated());

        return ApiResponse::success(new ApplicationResource($application->fresh()));
    }
}
