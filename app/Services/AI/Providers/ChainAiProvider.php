<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChainAiProvider implements AiProviderInterface
{
    /**
     * @param array<int, AiProviderInterface> $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    private ?AiProviderInterface $resolvedProvider = null;

    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return $this->attempt(
            operation: 'job_analysis',
            callback: fn (AiProviderInterface $provider): ?array => $provider->analyzeJob($job, $prompt),
            context: ['job_id' => $job->id]
        );
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->attempt(
            operation: 'match_explanation',
            callback: fn (AiProviderInterface $provider): ?array => $provider->explainMatch($profile, $job, $scoreBreakdown, $prompt),
            context: ['job_id' => $job->id, 'profile_id' => $profile->id]
        );
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->attempt(
            operation: 'resume_tailoring',
            callback: fn (AiProviderInterface $provider): ?array => $provider->tailorResume($profile, $job, $resumeContext, $prompt),
            context: ['job_id' => $job->id, 'profile_id' => $profile->id]
        );
    }

    public function name(): string
    {
        if ($this->resolvedProvider) {
            return $this->resolvedProvider->name();
        }

        return 'chain('.implode(',', array_map(
            static fn (AiProviderInterface $provider): string => $provider->name(),
            $this->providers
        )).')';
    }

    public function model(): ?string
    {
        return $this->resolvedProvider?->model();
    }

    /**
     * @param callable(AiProviderInterface): (?array<string, mixed>) $callback
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function attempt(string $operation, callable $callback, array $context): ?array
    {
        $this->resolvedProvider = null;
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                $response = $callback($provider);

                if ($response !== null) {
                    $this->resolvedProvider = $provider;

                    return $response;
                }
            } catch (AiProviderException|Throwable $exception) {
                $lastException = $exception;

                Log::warning('AI chain provider attempt failed.', array_merge($context, [
                    'provider' => $provider->name(),
                    'operation' => $operation,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]));
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        return null;
    }
}
