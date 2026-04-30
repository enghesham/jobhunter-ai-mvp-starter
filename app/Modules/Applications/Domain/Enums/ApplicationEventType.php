<?php

namespace App\Modules\Applications\Domain\Enums;

enum ApplicationEventType: string
{
    case ApplicationCreated = 'application_created';
    case StatusChanged = 'status_changed';
    case ResumeLinked = 'resume_linked';
    case AppliedManually = 'applied_manually';
    case InterviewScheduled = 'interview_scheduled';
    case FollowUpScheduled = 'follow_up_scheduled';
    case FollowUpSent = 'follow_up_sent';
    case ResponseReceived = 'response_received';
    case OfferReceived = 'offer_received';
    case Rejected = 'rejected';
    case Archived = 'archived';
    case NoteAdded = 'note_added';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
