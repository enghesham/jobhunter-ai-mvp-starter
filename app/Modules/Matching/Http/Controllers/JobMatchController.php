<?php

namespace App\Modules\Matching\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Jobs\Http\Resources\JobMatchResource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class JobMatchController extends Controller
{
    public function index(): JsonResponse
    {
        $matches = JobMatch::query()
            ->where('user_id', auth()->id())
            ->with(['job', 'profile'])
            ->latest('matched_at')
            ->paginate();

        return ApiResponse::success(JobMatchResource::collection($matches)->response()->getData(true));
    }
}
