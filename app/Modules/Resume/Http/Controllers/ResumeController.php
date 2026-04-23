<?php

namespace App\Modules\Resume\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTailoredResumeJob;
use App\Modules\Jobs\Domain\Models\Job;
use Illuminate\Http\JsonResponse;

class ResumeController extends Controller
{
    public function generate(Job $job): JsonResponse
    {
        GenerateTailoredResumeJob::dispatch($job->id, 1);

        return response()->json([
            'message' => 'Tailored resume generation queued',
            'job_id' => $job->id,
        ]);
    }
}
