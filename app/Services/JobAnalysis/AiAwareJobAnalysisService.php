<?php

namespace App\Services\JobAnalysis;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\AnalyzeJobPrompt;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use App\Services\JobAnalysis\Support\JobAnalysisResultValidator;
use Illuminate\Support\Facades\Log;

class AiAwareJobAnalysisService implements JobAnalysisServiceInterface
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly BasicKeywordJobAnalysisService $fallback,
        private readonly AnalyzeJobPrompt $prompt,
        private readonly JobAnalysisResultValidator $validator,
    ) {
    }

    public function analyze(Job $job): array
    {
        try {
            $response = $this->provider->analyzeJob($job, $this->prompt->build($job));

            if ($response === null) {
                return $this->fallback($job);
            }

            $validated = $this->validator->validate($response);

            if ($validated !== null) {
                return array_merge($validated, [
                    'ai_provider' => $this->provider->name(),
                    'ai_model' => $this->provider->model(),
                    'ai_generated_at' => now(),
                    'ai_confidence_score' => (int) ($response['confidence_score'] ?? 0),
                    'ai_raw_response' => (app()->isLocal() || config('app.debug')) ? ($response['_raw_response'] ?? null) : null,
                ]);
            }

            Log::warning('AI provider returned invalid job analysis payload.', [
                'provider' => $this->provider->name(),
                'job_id' => $job->id,
            ]);
        } catch (AiProviderException|\Throwable $exception) {
            Log::warning('AI provider job analysis failed.', [
                'provider' => $this->provider->name(),
                'operation' => 'job_analysis',
                'job_id' => $job->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->fallback($job);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(Job $job): array
    {
        return array_merge($this->fallback->analyze($job), [
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
        ]);
    }
}
