<?php

namespace App\Services\JobAnalysis;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\AnalyzeJobPrompt;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use App\Services\JobAnalysis\Support\JobAnalysisResultValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if ($job->exists) {
            $job->loadMissing('analysis');
        }
        $promptVersion = $this->prompt->version();
        $inputHash = $this->inputHash($job, $promptVersion);

        if ($cached = $this->cached($job, $promptVersion, $inputHash)) {
            $this->logResult($job, true, (bool) $cached['fallback_used'], 0, $cached['ai_provider']);

            return array_merge($cached, ['cache_hit' => true]);
        }

        $startedAt = microtime(true);

        try {
            $response = $this->provider->analyzeJob($job, $this->prompt->build($job));

            if ($response === null) {
                return $this->fallback($job, $promptVersion, $inputHash, $startedAt);
            }

            $validated = $this->validator->validate($response);

            if ($validated !== null) {
                $result = array_merge($validated, [
                    'ai_provider' => $this->provider->name(),
                    'ai_model' => $this->provider->model(),
                    'ai_generated_at' => now(),
                    'ai_confidence_score' => (int) ($response['confidence_score'] ?? 0),
                    'ai_raw_response' => (app()->isLocal() || config('app.debug')) ? ($response['_raw_response'] ?? null) : null,
                    'prompt_version' => $promptVersion,
                    'input_hash' => $inputHash,
                    'ai_duration_ms' => $this->durationMs($startedAt),
                    'fallback_used' => false,
                    'cache_hit' => false,
                ]);

                $this->logResult($job, false, false, (int) $result['ai_duration_ms'], $result['ai_provider']);

                return $result;
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

        return $this->fallback($job, $promptVersion, $inputHash, $startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(Job $job, string $promptVersion, string $inputHash, float $startedAt): array
    {
        $result = array_merge($this->fallback->analyze($job), [
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'ai_duration_ms' => $this->durationMs($startedAt),
            'fallback_used' => true,
            'cache_hit' => false,
        ]);

        $this->logResult($job, false, true, (int) $result['ai_duration_ms'], null);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cached(Job $job, string $promptVersion, string $inputHash): ?array
    {
        if (! config('jobhunter.ai_cache_enabled', true) || ! $job->exists) {
            return null;
        }

        $analysis = $job->analysis;

        if (! $analysis || $analysis->prompt_version !== $promptVersion || $analysis->input_hash !== $inputHash) {
            return null;
        }

        return [
            'required_skills' => $analysis->required_skills ?? [],
            'preferred_skills' => $analysis->preferred_skills ?? [],
            'must_have_skills' => $analysis->must_have_skills ?? [],
            'nice_to_have_skills' => $analysis->nice_to_have_skills ?? [],
            'seniority' => $analysis->seniority,
            'role_type' => $analysis->role_type,
            'domain_tags' => $analysis->domain_tags ?? [],
            'tech_stack' => $analysis->tech_stack ?? [],
            'responsibilities' => $analysis->responsibilities ?? [],
            'company_context' => $analysis->company_context,
            'ai_summary' => $analysis->ai_summary,
            'confidence_score' => $analysis->confidence_score,
            'ai_provider' => $analysis->ai_provider,
            'ai_model' => $analysis->ai_model,
            'ai_generated_at' => $analysis->ai_generated_at,
            'ai_confidence_score' => $analysis->ai_confidence_score,
            'ai_raw_response' => $analysis->ai_raw_response,
            'prompt_version' => $analysis->prompt_version,
            'input_hash' => $analysis->input_hash,
            'ai_duration_ms' => $analysis->ai_duration_ms,
            'fallback_used' => (bool) $analysis->fallback_used,
        ];
    }

    private function inputHash(Job $job, string $promptVersion): string
    {
        return hash('sha256', json_encode([
            'prompt_version' => $promptVersion,
            'title' => $job->title,
            'company_name' => $job->company_name,
            'location' => $job->location,
            'remote_type' => $job->remote_type,
            'employment_type' => $job->employment_type,
            'description' => Str::limit((string) ($job->description_clean ?: $job->description_raw), 12000, ''),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function logResult(Job $job, bool $cacheHit, bool $fallbackUsed, int $durationMs, ?string $provider): void
    {
        Log::info('AI job analysis completed.', [
            'provider' => $provider,
            'operation' => 'job_analysis',
            'job_id' => $job->id,
            'prompt_version' => $this->prompt->version(),
            'cache_hit' => $cacheHit,
            'fallback_used' => $fallbackUsed,
            'duration_ms' => $durationMs,
        ]);
    }
}
