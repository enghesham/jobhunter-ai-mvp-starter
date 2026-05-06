<?php

namespace App\Modules\Copilot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Copilot\Http\Requests\UpdateOpportunityPreferencesRequest;
use App\Services\Copilot\OpportunityPreferenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityPreferenceController extends Controller
{
    public function show(Request $request, OpportunityPreferenceService $preferences): JsonResponse
    {
        return ApiResponse::success($preferences->responseFor($request->user()));
    }

    public function update(UpdateOpportunityPreferencesRequest $request, OpportunityPreferenceService $preferences): JsonResponse
    {
        $preferences->update($request->user(), $request->validated());

        return ApiResponse::success($preferences->responseFor($request->user()));
    }
}
