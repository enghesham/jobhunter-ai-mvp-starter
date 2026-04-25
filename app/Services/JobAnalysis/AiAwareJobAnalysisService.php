<?php

namespace App\Services\JobAnalysis;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\JobAnalysisPromptFactory;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use App\Services\JobAnalysis\Support\JobAnalysisResultValidator;
use Illuminate\Support\Facades\Log;

class AiAwareJobAnalysisService implements JobAnalysisServiceInterface
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly BasicKeywordJobAnalysisService $fallback,
        private readonly JobAnalysisPromptFactory $promptFactory,
        private readonly JobAnalysisResultValidator $validator,
    ) {
    }

    public function analyze(Job $job): array
    {
        try {
            $response = $this->provider->analyzeJob($job, $this->promptFactory->build($job));

            if ($response === null) {
                return $this->fallback->analyze($job);
            }

            $validated = $this->validator->validate($response);

            if ($validated !== null) {
                return $validated;
            }

            Log::warning('AI provider returned invalid job analysis payload.', [
                'provider' => $this->provider->name(),
                'job_id' => $job->id,
            ]);
        } catch (AiProviderException|\Throwable $exception) {
            Log::warning('AI provider job analysis failed.', [
                'provider' => $this->provider->name(),
                'job_id' => $job->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->fallback->analyze($job);
    }
}
