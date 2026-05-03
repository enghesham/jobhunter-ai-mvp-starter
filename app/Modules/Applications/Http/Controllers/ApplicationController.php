<?php

namespace App\Modules\Applications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Http\Requests\StoreApplicationEventRequest;
use App\Modules\Applications\Http\Requests\StoreApplicationRequest;
use App\Modules\Applications\Http\Requests\UpdateApplicationRequest;
use App\Modules\Applications\Http\Resources\ApplicationEventResource;
use App\Modules\Applications\Http\Resources\ApplicationMaterialResource;
use App\Modules\Applications\Http\Resources\ApplicationResource;
use App\Services\Applications\ApplicationMaterialGenerationService;
use App\Services\Applications\ApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        $applications = Application::query()
            ->where('user_id', auth()->id())
            ->with(['job', 'profile', 'jobPath', 'applyPackage', 'tailoredResume'])
            ->latest()
            ->paginate();

        return ApiResponse::success(ApplicationResource::collection($applications)->response()->getData(true));
    }

    public function store(StoreApplicationRequest $request, ApplicationService $applicationService): JsonResponse
    {
        $application = $applicationService->create($request->validated() + ['user_id' => auth()->id()]);

        return ApiResponse::success(new ApplicationResource($application->load(['job', 'profile', 'jobPath', 'applyPackage', 'tailoredResume'])), 201);
    }

    public function show(Application $application): JsonResponse
    {
        $this->authorize('view', $application);
        return ApiResponse::success(new ApplicationResource($application->load(['job', 'profile', 'jobPath', 'applyPackage', 'tailoredResume', 'events', 'materials'])));
    }

    public function update(UpdateApplicationRequest $request, Application $application, ApplicationService $applicationService): JsonResponse
    {
        $this->authorize('update', $application);
        $application = $applicationService->update(
            $application,
            $request->validated() + ['user_id' => auth()->id()]
        );

        return ApiResponse::success(new ApplicationResource($application->load(['job', 'profile', 'jobPath', 'applyPackage', 'tailoredResume', 'events', 'materials'])));
    }

    public function storeEvent(
        StoreApplicationEventRequest $request,
        Application $application,
        ApplicationService $applicationService,
    ): JsonResponse {
        $this->authorize('update', $application);

        $event = $applicationService->addEvent(
            $application,
            $request->validated() + ['user_id' => auth()->id()]
        );

        return ApiResponse::success(new ApplicationEventResource($event), 201);
    }

    public function materials(Application $application): JsonResponse
    {
        $this->authorize('view', $application);

        return ApiResponse::success(ApplicationMaterialResource::collection($application->load('materials')->materials));
    }

    public function generateMaterials(
        Request $request,
        Application $application,
        ApplicationMaterialGenerationService $generationService,
    ): JsonResponse {
        $this->authorize('update', $application);

        $materials = $generationService->generate(
            $application,
            (bool) $request->boolean('force'),
            $request->input('sections', []),
        );

        return ApiResponse::success(ApplicationMaterialResource::collection($materials), 201);
    }

    public function destroy(Application $application): JsonResponse
    {
        $this->authorize('delete', $application);
        $application->delete();

        return ApiResponse::success(['message' => 'Application deleted']);
    }
}
