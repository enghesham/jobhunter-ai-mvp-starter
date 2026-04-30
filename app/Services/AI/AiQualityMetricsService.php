<?php

namespace App\Services\AI;

use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AiQualityMetricsService
{
    private const OPERATIONS = [
        'job_analysis' => 'Job Analysis',
        'match_explanation' => 'Match Explanation',
        'resume_tailoring' => 'Resume Tailoring',
        'application_materials' => 'Application Materials',
    ];

    /**
     * @return array<string, mixed>
     */
    public function reportForUser(int $userId): array
    {
        $records = $this->recordsForUser($userId);
        $logInsights = $this->logInsights();

        return [
            'summary' => $this->summary($records, $logInsights),
            'providers' => $this->providers($records, $logInsights),
            'operations' => $this->operations($records, $logInsights),
            'cache' => $this->cacheStats($records, $logInsights),
            'top_errors' => $logInsights['top_errors'],
            'recent_runs' => $this->recentRuns($records),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recordsForUser(int $userId): Collection
    {
        return collect()
            ->merge($this->jobAnalysisRecords($userId))
            ->merge($this->matchRecords($userId))
            ->merge($this->resumeRecords($userId))
            ->merge($this->applicationMaterialRecords($userId));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function jobAnalysisRecords(int $userId): Collection
    {
        return JobAnalysis::query()
            ->whereHas('job', fn ($query) => $query->where('user_id', $userId))
            ->with('job:id,title,company_name,user_id')
            ->get()
            ->map(fn (JobAnalysis $analysis): array => $this->record(
                $analysis,
                'job_analysis',
                'job_analysis',
                $analysis->id,
                trim(($analysis->job?->title ?? 'Job').' - '.($analysis->job?->company_name ?? 'Unknown company')),
                $analysis->analyzed_at
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function matchRecords(int $userId): Collection
    {
        return JobMatch::query()
            ->where('user_id', $userId)
            ->with(['job:id,title,company_name', 'profile:id,full_name'])
            ->get()
            ->map(fn (JobMatch $match): array => $this->record(
                $match,
                'match_explanation',
                'job_match',
                $match->id,
                trim(($match->job?->title ?? 'Job').' - '.($match->profile?->full_name ?? 'Candidate')),
                $match->matched_at
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resumeRecords(int $userId): Collection
    {
        return TailoredResume::query()
            ->where('user_id', $userId)
            ->with(['job:id,title,company_name', 'profile:id,full_name'])
            ->get()
            ->map(fn (TailoredResume $resume): array => $this->record(
                $resume,
                'resume_tailoring',
                'tailored_resume',
                $resume->id,
                trim(($resume->profile?->full_name ?? 'Candidate').' - '.($resume->job?->title ?? 'Resume')),
                $resume->created_at
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function applicationMaterialRecords(int $userId): Collection
    {
        return ApplicationMaterial::query()
            ->where('user_id', $userId)
            ->with(['job:id,title,company_name', 'profile:id,full_name'])
            ->get()
            ->map(fn (ApplicationMaterial $material): array => $this->record(
                $material,
                'application_materials',
                'application_material',
                $material->id,
                trim(($material->title ?? 'Application material').' - '.($material->job?->title ?? 'Job')),
                $material->created_at
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Model $model, string $operation, string $resourceType, int $resourceId, string $label, mixed $createdAt): array
    {
        $fallbackUsed = (bool) ($model->fallback_used ?? false);
        $provider = $this->providerKey($model->ai_provider ?? null, $fallbackUsed);
        $duration = $model->ai_duration_ms;
        $confidence = $model->ai_confidence_score ?? $model->confidence_score ?? null;
        $generatedAt = $model->ai_generated_at ?? null;

        return [
            'operation' => $operation,
            'operation_label' => self::OPERATIONS[$operation] ?? Str::headline($operation),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'label' => $label,
            'provider' => $provider,
            'provider_label' => $this->providerLabel($provider),
            'model' => $model->ai_model ?? null,
            'prompt_version' => $model->prompt_version ?? null,
            'input_hash' => $model->input_hash ?? null,
            'ai_duration_ms' => is_numeric($duration) ? (int) $duration : null,
            'fallback_used' => $fallbackUsed,
            'ai_success' => ! $fallbackUsed && filled($model->ai_provider ?? null),
            'ai_confidence_score' => is_numeric($confidence) ? (int) $confidence : null,
            'ai_generated_at' => $this->dateToIso($generatedAt),
            'created_at' => $this->dateToIso($createdAt),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @param array<string, mixed> $logInsights
     * @return array<string, mixed>
     */
    private function summary(Collection $records, array $logInsights): array
    {
        $total = $records->count();
        $aiSuccess = $records->where('ai_success', true)->count();
        $fallback = $records->where('fallback_used', true)->count();
        $durations = $records->pluck('ai_duration_ms')->filter(fn ($value) => is_numeric($value));
        $confidenceScores = $records->pluck('ai_confidence_score')->filter(fn ($value) => is_numeric($value));

        return [
            'total_runs' => $total,
            'ai_success_runs' => $aiSuccess,
            'fallback_runs' => $fallback,
            'ai_success_rate' => $this->rate($aiSuccess, $total),
            'fallback_rate' => $this->rate($fallback, $total),
            'average_duration_ms' => $this->average($durations),
            'average_confidence_score' => $this->average($confidenceScores),
            'provider_error_count' => (int) $logInsights['total_errors'],
            'cache_hit_rate' => $logInsights['cache_total'] > 0
                ? $this->rate((int) $logInsights['cache_hits'], (int) $logInsights['cache_total'])
                : null,
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @param array<string, mixed> $logInsights
     * @return array<int, array<string, mixed>>
     */
    private function providers(Collection $records, array $logInsights): array
    {
        $providerNames = $this->providerNames($records, $logInsights);

        return $providerNames
            ->map(function (string $provider) use ($records, $logInsights): array {
                $providerRecords = $records->where('provider', $provider);
                $total = $providerRecords->count();
                $success = $providerRecords->where('ai_success', true)->count();
                $fallback = $providerRecords->where('fallback_used', true)->count();
                $durations = $providerRecords->pluck('ai_duration_ms')->filter(fn ($value) => is_numeric($value));
                $confidenceScores = $providerRecords->pluck('ai_confidence_score')->filter(fn ($value) => is_numeric($value));
                $errors = (int) ($logInsights['error_counts_by_provider'][$provider] ?? 0);
                $cache = collect($logInsights['cache_events'])->where('provider', $provider);

                return [
                    'provider' => $provider,
                    'provider_label' => $this->providerLabel($provider),
                    'total_runs' => $total,
                    'ai_success_runs' => $success,
                    'fallback_runs' => $fallback,
                    'ai_success_rate' => $this->rate($success, $total),
                    'fallback_rate' => $this->rate($fallback, $total),
                    'average_duration_ms' => $this->average($durations),
                    'average_confidence_score' => $this->average($confidenceScores),
                    'provider_error_count' => $errors,
                    'cache_hit_rate' => $cache->count() > 0 ? $this->rate($cache->where('cache_hit', true)->count(), $cache->count()) : null,
                    'top_operation' => $this->topOperationLabel($providerRecords),
                ];
            })
            ->sortByDesc(fn (array $provider): int => ((int) $provider['total_runs'] * 1000) + (int) $provider['provider_error_count'])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @param array<string, mixed> $logInsights
     * @return array<int, array<string, mixed>>
     */
    private function operations(Collection $records, array $logInsights): array
    {
        return collect(self::OPERATIONS)
            ->map(function (string $label, string $operation) use ($records, $logInsights): array {
                $operationRecords = $records->where('operation', $operation);
                $total = $operationRecords->count();
                $success = $operationRecords->where('ai_success', true)->count();
                $fallback = $operationRecords->where('fallback_used', true)->count();
                $durations = $operationRecords->pluck('ai_duration_ms')->filter(fn ($value) => is_numeric($value));
                $cache = collect($logInsights['cache_events'])->where('operation', $operation);

                return [
                    'operation' => $operation,
                    'operation_label' => $label,
                    'total_runs' => $total,
                    'ai_success_runs' => $success,
                    'fallback_runs' => $fallback,
                    'ai_success_rate' => $this->rate($success, $total),
                    'fallback_rate' => $this->rate($fallback, $total),
                    'average_duration_ms' => $this->average($durations),
                    'cache_hit_rate' => $cache->count() > 0 ? $this->rate($cache->where('cache_hit', true)->count(), $cache->count()) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @param array<string, mixed> $logInsights
     * @return array<string, mixed>
     */
    private function cacheStats(Collection $records, array $logInsights): array
    {
        $hashes = $records->pluck('input_hash')->filter()->values();
        $duplicateGroups = $hashes->countBy()->filter(fn (int $count) => $count > 1)->count();
        $duplicateRecords = $hashes->count() - $hashes->unique()->count();

        return [
            'observed_hits' => (int) $logInsights['cache_hits'],
            'observed_misses' => (int) $logInsights['cache_misses'],
            'observed_events' => (int) $logInsights['cache_total'],
            'hit_rate' => $logInsights['cache_total'] > 0
                ? $this->rate((int) $logInsights['cache_hits'], (int) $logInsights['cache_total'])
                : null,
            'cacheable_records' => $hashes->count(),
            'unique_input_hashes' => $hashes->unique()->count(),
            'duplicate_input_hash_groups' => $duplicateGroups,
            'duplicate_input_hash_records' => $duplicateRecords,
            'source' => 'application_logs_and_persisted_input_hashes',
            'note' => 'Observed hit rate uses logged AI cache events. Duplicate hashes show reuse potential in persisted records.',
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function recentRuns(Collection $records): array
    {
        return $records
            ->sortByDesc(fn (array $record): string => (string) ($record['ai_generated_at'] ?? $record['created_at'] ?? ''))
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function logInsights(): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return [
                'cache_events' => [],
                'cache_hits' => 0,
                'cache_misses' => 0,
                'cache_total' => 0,
                'top_errors' => [],
                'total_errors' => 0,
                'error_counts_by_provider' => [],
            ];
        }

        $lines = $this->recentLogLines($path, 5000);
        $cacheEvents = [];
        $errors = [];

        foreach ($lines as $line) {
            if (! str_contains($line, 'AI ')) {
                continue;
            }

            $context = $this->jsonContext($line);

            if (str_contains($line, ' completed.') && array_key_exists('cache_hit', $context)) {
                $cacheEvents[] = [
                    'provider' => $this->providerKey($context['provider'] ?? null, (bool) ($context['fallback_used'] ?? false)),
                    'operation' => (string) ($context['operation'] ?? $this->operationFromLine($line)),
                    'cache_hit' => (bool) $context['cache_hit'],
                    'fallback_used' => (bool) ($context['fallback_used'] ?? false),
                    'duration_ms' => is_numeric($context['duration_ms'] ?? null) ? (int) $context['duration_ms'] : null,
                ];
            }

            if (str_contains($line, ' failed.')) {
                $provider = $this->providerKey($context['provider'] ?? null, false);
                $operation = (string) ($context['operation'] ?? $this->operationFromLine($line));
                $message = $this->sanitizeMessage((string) ($context['message'] ?? $this->lineMessage($line)));
                $key = $provider.'|'.$operation.'|'.$message;

                if (! isset($errors[$key])) {
                    $errors[$key] = [
                        'provider' => $provider,
                        'provider_label' => $this->providerLabel($provider),
                        'operation' => $operation,
                        'operation_label' => self::OPERATIONS[$operation] ?? Str::headline($operation),
                        'message' => $message,
                        'count' => 0,
                    ];
                }

                $errors[$key]['count']++;
            }
        }

        $topErrors = collect($errors)
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();

        $cache = collect($cacheEvents);

        return [
            'cache_events' => $cacheEvents,
            'cache_hits' => $cache->where('cache_hit', true)->count(),
            'cache_misses' => $cache->where('cache_hit', false)->count(),
            'cache_total' => $cache->count(),
            'top_errors' => $topErrors,
            'total_errors' => collect($errors)->sum('count'),
            'error_counts_by_provider' => collect($errors)
                ->groupBy('provider')
                ->map(fn (Collection $providerErrors): int => $providerErrors->sum('count'))
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function recentLogLines(string $path, int $limit): array
    {
        $maxBytes = 2 * 1024 * 1024;

        if ((int) File::size($path) <= $maxBytes) {
            return array_slice(@file($path, FILE_IGNORE_NEW_LINES) ?: [], -$limit);
        }

        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return [];
        }

        fseek($handle, -$maxBytes, SEEK_END);
        fgets($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return array_slice(preg_split('/\r\n|\r|\n/', $contents) ?: [], -$limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonContext(string $line): array
    {
        if (! preg_match('/(\{.*\})\s*$/', $line, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function operationFromLine(string $line): string
    {
        return match (true) {
            str_contains($line, 'job analysis') => 'job_analysis',
            str_contains($line, 'match explanation') => 'match_explanation',
            str_contains($line, 'resume tailoring') => 'resume_tailoring',
            str_contains($line, 'application material') => 'application_materials',
            default => 'unknown',
        };
    }

    private function lineMessage(string $line): string
    {
        return trim((string) Str::after($line, ':'));
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/([?&]key=)[^&\s"]+/i', '$1[redacted]', $message) ?? $message;
        $message = preg_replace('/(api[_-]?key["\':=]\s*)[^,\s"}]+/i', '$1[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/sk-[A-Za-z0-9_\-]+/i', 'sk-[redacted]', $message) ?? $message;

        return Str::limit($message, 260, '...');
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     * @param array<string, mixed> $logInsights
     * @return Collection<int, string>
     */
    private function providerNames(Collection $records, array $logInsights): Collection
    {
        $configured = collect([
            config('jobhunter.ai_provider'),
            ...((array) config('jobhunter.ai_provider_chain', [])),
            'openrouter',
            'gemini',
            'groq',
            'openai',
            'ollama',
            'python_microservice',
            'deterministic_fallback',
        ]);

        return $configured
            ->merge($records->pluck('provider'))
            ->merge(array_keys($logInsights['error_counts_by_provider'] ?? []))
            ->filter(fn ($provider): bool => filled($provider))
            ->map(fn ($provider): string => strtolower((string) $provider))
            ->unique()
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $records
     */
    private function topOperationLabel(Collection $records): ?string
    {
        if ($records->isEmpty()) {
            return null;
        }

        $operation = (string) $records->countBy('operation')->sortDesc()->keys()->first();

        return self::OPERATIONS[$operation] ?? Str::headline($operation);
    }

    private function providerKey(?string $provider, bool $fallbackUsed): string
    {
        if (filled($provider)) {
            return strtolower(trim($provider));
        }

        return $fallbackUsed ? 'deterministic_fallback' : 'unknown';
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'openrouter' => 'OpenRouter',
            'gemini' => 'Gemini',
            'groq' => 'Groq',
            'ollama' => 'Ollama',
            'python_microservice' => 'Python Microservice',
            'deterministic_fallback' => 'Deterministic Fallback',
            default => Str::headline(str_replace(['_', '-'], ' ', $provider)),
        };
    }

    private function dateToIso(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return filled($value) ? (string) $value : null;
    }

    private function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : null;
    }

    /**
     * @param Collection<int, mixed> $values
     */
    private function average(Collection $values): ?float
    {
        return $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
    }
}
