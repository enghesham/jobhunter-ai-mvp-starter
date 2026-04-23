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
        public ?string $remoteType,
        public ?string $employmentType,
        public ?string $descriptionRaw,
        public ?string $applyUrl,
        public ?string $salaryText = null,
        public ?CarbonImmutable $postedAt = null,
    ) {
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->externalId ?: '',
            mb_strtolower($this->companyName),
            mb_strtolower($this->title),
            mb_strtolower($this->location ?: ''),
            mb_strtolower($this->applyUrl ?: ''),
        ]));
    }
}
