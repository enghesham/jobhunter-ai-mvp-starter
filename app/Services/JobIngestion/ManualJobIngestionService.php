<?php

namespace App\Services\JobIngestion;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\DTO\NormalizedJobData;
use Carbon\CarbonImmutable;

class ManualJobIngestionService
{
    public function __construct(private readonly JobUpsertService $upsertService)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     * @return array{created: int, updated: int, skipped: int, jobs: array<int, \App\Modules\Jobs\Domain\Models\Job>}
     */
    public function ingest(JobSource $source, array $jobs): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'jobs' => []];

        foreach ($jobs as $payload) {
            $normalized = $this->normalize($source, $payload);
            $upsert = $this->upsertService->upsert($source, $normalized);

            if ($upsert['created']) {
                $result['created']++;
            } elseif ($upsert['changed']) {
                $result['updated']++;
            } else {
                $result['skipped']++;
            }

            if (isset($payload['status']) && is_string($payload['status'])) {
                $upsert['job']->status = $payload['status'];
                $upsert['job']->save();
            }

            $result['jobs'][] = $upsert['job']->fresh(['source']);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function normalize(JobSource $source, array $payload): NormalizedJobData
    {
        $companyName = $payload['company_name'] ?? $payload['company'] ?? $source->company_name ?? $source->name;
        $remoteType = isset($payload['remote_type']) && is_string($payload['remote_type'])
            ? $payload['remote_type']
            : ((bool) ($payload['is_remote'] ?? false) ? 'remote' : null);

        return new NormalizedJobData(
            externalId: isset($payload['external_id']) ? (string) $payload['external_id'] : null,
            companyName: (string) $companyName,
            title: (string) $payload['title'],
            location: isset($payload['location']) ? (string) $payload['location'] : null,
            isRemote: (bool) ($payload['is_remote'] ?? $remoteType === 'remote'),
            remoteType: $remoteType,
            employmentType: isset($payload['employment_type']) ? (string) $payload['employment_type'] : null,
            descriptionRaw: isset($payload['description']) ? (string) $payload['description'] : null,
            applyUrl: isset($payload['url']) ? (string) $payload['url'] : null,
            rawPayload: isset($payload['raw_payload']) && is_array($payload['raw_payload']) ? $payload['raw_payload'] : $payload,
            salaryText: null,
            postedAt: isset($payload['posted_at']) ? CarbonImmutable::parse((string) $payload['posted_at']) : null,
        );
    }
}
