<?php

namespace App\Services\JobIngestion\Fetchers;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\Contracts\JobSourceFetcherInterface;
use App\Services\JobIngestion\DTO\NormalizedJobData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LeverJobSourceFetcher implements JobSourceFetcherInterface
{
    public function supports(JobSource $source): bool
    {
        return $source->type === 'lever';
    }

    public function fetch(JobSource $source): array
    {
        $site = $source->meta['site'] ?? $this->extractSite($source->base_url);

        if (! is_string($site) || $site === '') {
            return [];
        }

        $response = Http::timeout(20)
            ->retry(2, 300)
            ->acceptJson()
            ->get("https://api.lever.co/v0/postings/{$site}", [
                'mode' => 'json',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json() ?: [])
            ->map(fn (array $payload): NormalizedJobData => $this->normalize($payload, $source))
            ->filter(fn (NormalizedJobData $job): bool => $job->title !== '')
            ->values()
            ->all();
    }

    private function normalize(array $payload, JobSource $source): NormalizedJobData
    {
        $categories = Arr::get($payload, 'categories', []);
        $location = Arr::get($categories, 'location');
        $commitment = Arr::get($categories, 'commitment');
        $team = Arr::get($categories, 'team');

        return new NormalizedJobData(
            externalId: (string) Arr::get($payload, 'id'),
            companyName: $source->company_name ?: $source->name,
            title: trim((string) Arr::get($payload, 'text', '')),
            location: is_string($location) ? $location : null,
            remoteType: $this->detectRemoteType((string) ($location ?? '')),
            employmentType: is_string($commitment) ? $commitment : null,
            descriptionRaw: $this->description($payload, is_string($team) ? $team : null),
            applyUrl: Arr::get($payload, 'hostedUrl') ?: Arr::get($payload, 'applyUrl'),
            salaryText: null,
            postedAt: $this->parseDate(Arr::get($payload, 'createdAt')),
        );
    }

    private function description(array $payload, ?string $team): string
    {
        $lists = collect(Arr::get($payload, 'lists', []))
            ->map(fn (array $item): string => trim(((string) Arr::get($item, 'text', '')).' '.((string) Arr::get($item, 'content', ''))))
            ->filter()
            ->implode("\n\n");

        return trim(implode("\n\n", array_filter([
            $team ? "Team: {$team}" : null,
            Arr::get($payload, 'descriptionPlain') ?: Arr::get($payload, 'description'),
            $lists,
        ])));
    }

    private function extractSite(string $baseUrl): ?string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH);
        $site = trim((string) $path, '/');

        return $site !== '' ? Str::before($site, '/') : null;
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
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampMs((int) $value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }
}
