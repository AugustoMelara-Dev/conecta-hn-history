<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Catalog\Enums\CatalogImportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogImportStatusTest extends TestCase
{
    /** @return iterable<string, array{CatalogImportStatus, CatalogImportStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'upload is queued' => [CatalogImportStatus::Uploaded, CatalogImportStatus::Queued];
        yield 'queue starts processing' => [CatalogImportStatus::Queued, CatalogImportStatus::Processing];
        yield 'analysis requires review' => [CatalogImportStatus::Processing, CatalogImportStatus::NeedsReview];
        yield 'provider failure is recorded' => [CatalogImportStatus::Processing, CatalogImportStatus::Failed];
        yield 'failed analysis can retry' => [CatalogImportStatus::Failed, CatalogImportStatus::Queued];
        yield 'review can approve' => [CatalogImportStatus::NeedsReview, CatalogImportStatus::Approved];
        yield 'review can reject' => [CatalogImportStatus::NeedsReview, CatalogImportStatus::Rejected];
        yield 'approved draft can publish' => [CatalogImportStatus::Approved, CatalogImportStatus::Published];
    }

    #[Test]
    #[DataProvider('validTransitions')]
    public function it_allows_only_explicit_workflow_edges(
        CatalogImportStatus $from,
        CatalogImportStatus $to,
    ): void {
        self::assertTrue($from->canTransitionTo($to));
    }

    #[Test]
    public function terminal_and_unsafe_shortcuts_are_rejected(): void
    {
        self::assertFalse(CatalogImportStatus::Uploaded->canTransitionTo(CatalogImportStatus::Published));
        self::assertFalse(CatalogImportStatus::NeedsReview->canTransitionTo(CatalogImportStatus::Published));
        self::assertFalse(CatalogImportStatus::Published->canTransitionTo(CatalogImportStatus::Queued));
        self::assertFalse(CatalogImportStatus::Rejected->canTransitionTo(CatalogImportStatus::Queued));
    }
}
