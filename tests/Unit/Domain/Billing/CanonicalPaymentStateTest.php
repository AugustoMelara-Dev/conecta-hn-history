<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Enums\CanonicalPaymentState;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Mappers\CanonicalPaymentStateMapper;
use App\Enums\TransactionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class CanonicalPaymentStateTest extends TestCase
{
    /** @return iterable<string, array{PaymentStatus|string, CanonicalPaymentState}> */
    public static function manualEvidenceMappings(): iterable
    {
        yield 'pending evidence remains pending' => [PaymentStatus::PENDING, CanonicalPaymentState::Pending];
        yield 'review evidence requires action' => [PaymentStatus::REVIEW, CanonicalPaymentState::RequiresAction];
        yield 'paid evidence succeeds' => [PaymentStatus::PAID, CanonicalPaymentState::Succeeded];
        yield 'legacy approved evidence succeeds without rewriting it' => ['approved', CanonicalPaymentState::Succeeded];
        yield 'rejected evidence fails' => [PaymentStatus::REJECTED, CanonicalPaymentState::Failed];
    }

    #[Test]
    #[DataProvider('manualEvidenceMappings')]
    public function it_maps_manual_payment_evidence_without_mutating_legacy_values(
        PaymentStatus|string $legacyStatus,
        CanonicalPaymentState $expected,
    ): void {
        self::assertSame($expected, CanonicalPaymentStateMapper::fromManualEvidence($legacyStatus));
    }

    /** @return iterable<string, array{string, CanonicalPaymentState}> */
    public static function subscriptionIntentMappings(): iterable
    {
        yield 'pending' => ['PENDING', CanonicalPaymentState::Pending];
        yield 'processing' => ['PROCESSING', CanonicalPaymentState::Processing];
        yield 'completed' => ['COMPLETED', CanonicalPaymentState::Succeeded];
        yield 'failed' => ['FAILED', CanonicalPaymentState::Failed];
    }

    #[Test]
    #[DataProvider('subscriptionIntentMappings')]
    public function it_maps_subscription_intent_states(string $legacyStatus, CanonicalPaymentState $expected): void
    {
        self::assertSame($expected, CanonicalPaymentStateMapper::fromSubscriptionIntent($legacyStatus));
    }

    /** @return iterable<string, array{TransactionStatus, CanonicalPaymentState}> */
    public static function transactionStatusMappings(): iterable
    {
        yield 'pending' => [TransactionStatus::PENDING, CanonicalPaymentState::Pending];
        yield 'processing' => [TransactionStatus::PROCESSING, CanonicalPaymentState::Processing];
        yield 'requires action' => [TransactionStatus::REQUIRES_ACTION, CanonicalPaymentState::RequiresAction];
        yield 'completed' => [TransactionStatus::COMPLETED, CanonicalPaymentState::Succeeded];
        yield 'failed' => [TransactionStatus::FAILED, CanonicalPaymentState::Failed];
        yield 'cancelled' => [TransactionStatus::CANCELLED, CanonicalPaymentState::Cancelled];
        yield 'refunded' => [TransactionStatus::REFUNDED, CanonicalPaymentState::Refunded];
        yield 'partially refunded' => [TransactionStatus::PARTIALLY_REFUNDED, CanonicalPaymentState::PartiallyRefunded];
        yield 'disputed' => [TransactionStatus::DISPUTED, CanonicalPaymentState::Disputed];
    }

    #[Test]
    #[DataProvider('transactionStatusMappings')]
    public function it_maps_every_transaction_status(TransactionStatus $legacyStatus, CanonicalPaymentState $expected): void
    {
        self::assertSame($expected, CanonicalPaymentStateMapper::fromTransactionStatus($legacyStatus));
    }

    /** @return iterable<string, array{string, CanonicalPaymentState}> */
    public static function orderPaymentStatusMappings(): iterable
    {
        yield 'pending' => ['pending', CanonicalPaymentState::Pending];
        yield 'paid' => ['paid', CanonicalPaymentState::Succeeded];
        yield 'failed' => ['failed', CanonicalPaymentState::Failed];
        yield 'refunded' => ['refunded', CanonicalPaymentState::Refunded];
    }

    #[Test]
    #[DataProvider('orderPaymentStatusMappings')]
    public function it_maps_order_payment_status_without_touching_order_workflow(string $legacyStatus, CanonicalPaymentState $expected): void
    {
        self::assertSame($expected, CanonicalPaymentStateMapper::fromOrderPaymentStatus($legacyStatus));
    }

    #[Test]
    public function only_succeeded_can_grant_an_entitlement(): void
    {
        foreach (CanonicalPaymentState::cases() as $state) {
            self::assertSame($state === CanonicalPaymentState::Succeeded, $state->isSuccessful());
            self::assertSame($state === CanonicalPaymentState::Succeeded, $state->canGrantEntitlement());
        }
    }

    #[Test]
    public function it_exposes_terminal_and_in_progress_payment_semantics(): void
    {
        self::assertTrue(CanonicalPaymentState::Succeeded->isTerminal());
        self::assertTrue(CanonicalPaymentState::Failed->isTerminal());
        self::assertTrue(CanonicalPaymentState::Cancelled->isTerminal());
        self::assertTrue(CanonicalPaymentState::Refunded->isTerminal());
        self::assertTrue(CanonicalPaymentState::Disputed->isTerminal());
        self::assertFalse(CanonicalPaymentState::PartiallyRefunded->isTerminal());

        self::assertTrue(CanonicalPaymentState::Pending->isInProgress());
        self::assertTrue(CanonicalPaymentState::Processing->isInProgress());
        self::assertTrue(CanonicalPaymentState::RequiresAction->isInProgress());
        self::assertFalse(CanonicalPaymentState::Succeeded->isInProgress());
    }

    #[Test]
    public function unknown_legacy_states_are_rejected_instead_of_being_granted_access(): void
    {
        $mappers = [
            static fn (): CanonicalPaymentState => CanonicalPaymentStateMapper::fromManualEvidence('mystery'),
            static fn (): CanonicalPaymentState => CanonicalPaymentStateMapper::fromSubscriptionIntent('MYSTERY'),
            static fn (): CanonicalPaymentState => CanonicalPaymentStateMapper::fromTransactionStatus('mystery'),
            static fn (): CanonicalPaymentState => CanonicalPaymentStateMapper::fromOrderPaymentStatus('mystery'),
        ];

        foreach ($mappers as $mapper) {
            try {
                $mapper();
                self::fail('Unknown payment states must not be coerced to a grantable state.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
