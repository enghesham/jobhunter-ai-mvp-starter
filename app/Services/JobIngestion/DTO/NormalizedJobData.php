<?php

namespace App\Services\JobIngestion\DTO;

use Carbon\CarbonImmutable;

readonly class NormalizedJobData
{
    public function __construct(
        public ?string $externalId,
        public string $companyName,
        public string $title,
        public ?string $location,
        public bool $isRemote,
        public ?string $remoteType,
        public ?string $employmentType,
        public ?string $descriptionRaw,
        public ?string $applyUrl,
        public ?array $rawPayload = null,
        public ?string $salaryText = null,
        public ?CarbonImmutable $postedAt = null,
    ) {
    }

    public function fingerprint(): string
    {
        return $this->sourceHash();
    }

    public function jobFingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($this->companyName),
            $this->normalize($this->title),
            $this->normalize($this->location),
        ]));
    }

    public function sourceHash(): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($this->companyName),
            $this->normalize($this->title),
            $this->normalize($this->location),
            $this->normalize($this->applyUrl),
        ]));
    }

    private function normalize(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
