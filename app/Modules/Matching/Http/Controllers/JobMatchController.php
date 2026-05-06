<?php

namespace App\Modules\Matching\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Jobs\Http\Resources\JobMatchResource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Services\Copilot\OpportunityPreferenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobMatchController extends Controller
{
    public function index(Request $request, OpportunityPreferenceService $preferences): JsonResponse
    {
        $query = JobMatch::query()
            ->where('user_id', auth()->id())
            ->with(['job', 'profile', 'jobPath']);

        if ($request->boolean('best_only')) {
            $defaultThreshold = $preferences->minMatchScore($request->user());

            $query
                ->whereNotNull('matched_at')
                ->where(function ($query) use ($defaultThreshold): void {
                    $query
                        ->where(function ($query) use ($defaultThreshold): void {
                            $query
                                ->whereNull('job_path_id')
                                ->where('overall_score', '>=', $defaultThreshold);
                        })
                        ->orWhereExists(function ($query): void {
                            $query
                                ->selectRaw('1')
                                ->from('job_paths')
                                ->whereColumn('job_paths.id', 'job_matches.job_path_id')
                                ->whereColumn('job_paths.user_id', 'job_matches.user_id')
                                ->whereColumn('job_matches.overall_score', '>=', 'job_paths.min_match_score');
                        });
                })
                ->where(function ($query): void {
                    $query
                        ->whereNull('recommendation_action')
                        ->orWhere('recommendation_action', '!=', 'skip');
                });
        }

        $matches = $query
            ->orderByDesc('overall_score')
            ->latest('matched_at')
            ->paginate();

        return ApiResponse::success(JobMatchResource::collection($matches)->response()->getData(true));
    }

    public function explanation(JobMatch $match): JsonResponse
    {
        abort_if($match->user_id !== auth()->id(), 404);

        return ApiResponse::success(new JobMatchResource($match->load(['job', 'profile', 'jobPath'])));
    }
}
