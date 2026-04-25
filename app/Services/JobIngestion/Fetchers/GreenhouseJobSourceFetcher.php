<?php

namespace App\Services\JobIngestion\Fetchers;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\Contracts\JobSourceFetcherInterface;
use App\Services\JobIngestion\DTO\NormalizedJobData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GreenhouseJobSourceFetcher implements JobSourceFetcherInterface
{
    public function supports(JobSource $source): bool
    {
        return $source->type === 'greenhouse';
    }

    public function fetch(JobSource $source): array
    {
        $boardToken = $source->meta['board_token'] ?? $this->extractBoardToken($source->base_url);

        if (! is_string($boardToken) || $boardToken === '') {
            return [];
        }

        $response = Http::timeout(20)
            ->retry(2, 300)
            ->acceptJson()
            ->get("https://boards-api.greenhouse.io/v1/boards/{$boardToken}/jobs", [
                'content' => 'true',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('jobs', []))
            ->map(fn (array $payload): NormalizedJobData => $this->normalize($payload, $source))
            ->filter(fn (NormalizedJobData $job): bool => $job->title !== '')
            ->values()
            ->all();
    }

    private function normalize(array $payload, JobSource $source): NormalizedJobData
    {
        $location = Arr::get($payload, 'location.name');

        return new NormalizedJobData(
            externalId: (string) Arr::get($payload, 'id'),
            companyName: $source->company_name ?: $source->name,
            title: trim((string) Arr::get($payload, 'title', '')),
            location: is_string($location) ? $location : null,
            isRemote: $this->detectRemoteType((string) ($location ?? '')) === 'remote',
            remoteType: $this->detectRemoteType((string) ($location ?? '')),
            employmentType: null,
            descriptionRaw: (string) Arr::get($payload, 'content', ''),
            applyUrl: Arr::get($payload, 'absolute_url'),
            rawPayload: $payload,
            salaryText: null,
            postedAt: $this->parseDate(Arr::get($payload, 'updated_at')),
        );
    }

    private function extractBoardToken(string $baseUrl): ?string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH);
        $token = trim((string) $path, '/');

        return $token !== '' ? Str::before($token, '/') : null;
    }

    private function detectRemoteType(string $location): ?string
    {
        $normalized = mb_strtolower($location);

        if (str_contains($normalized, 'remote')) {
            return 'remote';
        }

        if (str_contains($normalized, 'hybrid')) {
            return 'hybrid';
        }

        return null;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
