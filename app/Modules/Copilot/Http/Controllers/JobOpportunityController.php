<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Copilot\Domain\Models\JobOpportunity;
use App\Modules\Copilot\Http\Resources\JobOpportunityResource;
use App\Services\Copilot\OpportunityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class JobOpportunityController extends Controller
{
    public function index(Request $request, OpportunityService $opportunityService): JsonResponse
    {
        $opportunities = $opportunityService->list($request->user(), [
            'job_path_id' => $request->integer('job_path_id') ?: null,
            'include_hidden' => $request->boolean('include_hidden'),
            'show_duplicates' => $request->boolean('show_duplicates'),
        ]);

        return ApiResponse::success(JobOpportunityResource::collection($opportunities));
    }

    public function refresh(Request $request, OpportunityService $opportunityService): JsonResponse
    {
        $stats = $opportunityService->refresh(
            $request->user(),
            $request->integer('job_path_id') ?: null,
        );

        $opportunities = $opportunityService->list($request->user(), [
            'job_path_id' => $request->integer('job_path_id') ?: null,
        ]);

        return ApiResponse::success([
            'stats' => $stats,
            'opportunities' => JobOpportunityResource::collection($opportunities),
        ]);
    }

    public function evaluate(Request $request, JobOpportunity $opportunity, OpportunityService $opportunityService): JsonResponse
    {
        try {
            $opportunity = $opportunityService->evaluate($request->user(), $opportunity, $request->boolean('force'));
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new JobOpportunityResource($opportunity));
    }

    public function hide(Request $request, JobOpportunity $opportunity, OpportunityService $opportunityService): JsonResponse
    {
        $opportunity = $opportunityService->hide(
            $request->user(),
            $opportunity,
            $request->string('reason')->toString() ?: null,
        );

        return ApiResponse::success(new JobOpportunityResource($opportunity));
    }

    public function restore(Request $request, JobOpportunity $opportunity, OpportunityService $opportunityService): JsonResponse
    {
        $opportunity = $opportunityService->restore($request->user(), $opportunity);

        return ApiResponse::success(new JobOpportunityResource($opportunity));
    }
}
