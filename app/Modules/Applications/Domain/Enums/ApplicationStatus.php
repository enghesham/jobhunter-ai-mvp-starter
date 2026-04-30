<?php

namespace App\Modules\Applications\Domain\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case ReadyToApply = 'ready_to_apply';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Rejected = 'rejected';
    case Offer = 'offer';
    case Archived = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
