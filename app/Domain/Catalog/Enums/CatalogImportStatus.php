<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum CatalogImportStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Published = 'published';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Uploaded => [self::Queued, self::Rejected],
            self::Queued => [self::Processing, self::Rejected],
            self::Processing => [self::NeedsReview, self::Failed],
            self::NeedsReview => [self::Approved, self::Rejected],
            self::Approved => [self::Published, self::Rejected],
            self::Failed => [self::Queued, self::Rejected],
            self::Published, self::Rejected => [],
        }, true);
    }
}
