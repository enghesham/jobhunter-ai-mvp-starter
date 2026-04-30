<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiQualityMetricsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AiQualityController extends Controller
{
    public function __invoke(AiQualityMetricsService $metricsService): JsonResponse
    {
        abort_unless((bool) config('jobhunter.ai_quality_dashboard_enabled', true), 404);

        return ApiResponse::success($metricsService->reportForUser((int) auth()->id()));
    }
}
