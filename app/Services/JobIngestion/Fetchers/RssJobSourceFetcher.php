<?php

namespace App\Services\JobIngestion\Fetchers;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\Contracts\JobSourceFetcherInterface;
use App\Services\JobIngestion\DTO\NormalizedJobData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class RssJobSourceFetcher implements JobSourceFetcherInterface
{
    public function supports(JobSource $source): bool
    {
        return $source->type === 'rss';
    }

    public function fetch(JobSource $source): array
    {
        $response = Http::timeout((int) config('jobhunter.collection.fetch_timeout', 20))
            ->retry(2, 300)
            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
            ->get($source->base_url);

        if (! $response->successful()) {
            return [];
        }

        return collect($this->items($response->body()))
            ->map(fn (array $payload): NormalizedJobData => $this->normalize($payload, $source))
            ->filter(fn (NormalizedJobData $job): bool => $job->title !== '' && $job->applyUrl !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(string $xmlBody): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml) {
            return [];
        }

        if (isset($xml->channel->item)) {
            $items = [];

            foreach ($xml->channel->item as $item) {
                $items[] = $this->rssItem($item);
            }

            return $items;
        }

        if (isset($xml->entry)) {
            $entries = [];

            foreach ($xml->entry as $entry) {
                $entries[] = $this->atomEntry($entry);
            }

            return $entries;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function rssItem(mixed $item): array
    {
        $namespaces = $item->getNamespaces(true);
        $content = isset($namespaces['content'])
            ? (string) ($item->children($namespaces['content'])->encoded ?? '')
            : '';

        return [
            'external_id' => trim((string) ($item->guid ?? '')) ?: null,
            'title' => trim((string) ($item->title ?? '')),
            'link' => trim((string) ($item->link ?? '')) ?: null,
            'description' => trim($content ?: (string) ($item->description ?? '')),
            'published_at' => trim((string) ($item->pubDate ?? '')) ?: null,
            'categories' => collect($item->category ?? [])->map(fn ($category): string => trim((string) $category))->filter()->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function atomEntry(mixed $entry): array
    {
        $link = null;

        foreach ($entry->link ?? [] as $candidate) {
            $attributes = $candidate->attributes();
            $rel = (string) ($attributes['rel'] ?? 'alternate');

            if ($rel === 'alternate' || $link === null) {
                $link = trim((string) ($attributes['href'] ?? $candidate));
            }
        }

        return [
            'external_id' => trim((string) ($entry->id ?? '')) ?: null,
            'title' => trim((string) ($entry->title ?? '')),
            'link' => $link ?: null,
            'description' => trim((string) ($entry->summary ?? $entry->content ?? '')),
            'published_at' => trim((string) ($entry->published ?? $entry->updated ?? '')) ?: null,
            'categories' => collect($entry->category ?? [])->map(function ($category): string {
                $attributes = $category->attributes();

                return trim((string) ($attributes['term'] ?? $category));
            })->filter()->values()->all(),
        ];
    }

    private function normalize(array $payload, JobSource $source): NormalizedJobData
    {
        $rawTitle = trim((string) Arr::get($payload, 'title', ''));
        $companyFromTitle = $this->companyFromTitle($rawTitle);
        $title = $this->titleWithoutCompany($rawTitle);
        $description = (string) Arr::get($payload, 'description', '');
        $text = mb_strtolower($rawTitle.' '.$description.' '.implode(' ', Arr::wrap(Arr::get($payload, 'categories', []))));
        $remoteType = $this->detectRemoteType($text);

        return new NormalizedJobData(
            externalId: (string) (Arr::get($payload, 'external_id') ?: Arr::get($payload, 'link')),
            companyName: $source->company_name ?: $companyFromTitle ?: $source->name,
            title: $title,
            location: $this->location($source, $text),
            isRemote: $remoteType === 'remote',
            remoteType: $remoteType,
            employmentType: $this->employmentType($text),
            descriptionRaw: $description,
            applyUrl: Arr::get($payload, 'link'),
            rawPayload: $payload,
            salaryText: null,
            postedAt: $this->parseDate(Arr::get($payload, 'published_at')),
        );
    }

    private function companyFromTitle(string $title): ?string
    {
        if (preg_match('/\s+at\s+(?<company>[^|\\-–—]+)$/i', $title, $matches)) {
            return trim($matches['company']);
        }

        return null;
    }

    private function titleWithoutCompany(string $title): string
    {
        if (preg_match('/^(?<title>.+?)\s+at\s+[^|\\-–—]+$/i', $title, $matches)) {
            return trim($matches['title']);
        }

        return $title;
    }

    private function location(JobSource $source, string $text): ?string
    {
        $location = $source->meta['default_location'] ?? null;

        if (is_string($location) && $location !== '') {
            return $location;
        }

        if (str_contains($text, 'remote')) {
            return 'Remote';
        }

        return null;
    }

    private function detectRemoteType(string $text): ?string
    {
        if (str_contains($text, 'remote')) {
            return 'remote';
        }

        if (str_contains($text, 'hybrid')) {
            return 'hybrid';
        }

        if (str_contains($text, 'onsite') || str_contains($text, 'on-site')) {
            return 'onsite';
        }

        return null;
    }

    private function employmentType(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'full-time'), str_contains($text, 'full time') => 'full-time',
            str_contains($text, 'part-time'), str_contains($text, 'part time') => 'part-time',
            str_contains($text, 'contract') => 'contract',
            default => null,
        };
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
