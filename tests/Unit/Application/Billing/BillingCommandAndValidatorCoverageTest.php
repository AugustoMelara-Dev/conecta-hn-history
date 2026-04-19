<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Billing\Handlers\RequestUpgradeHandlerInterface;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Billing\Results\RequestUpgradeResult;
use App\Application\Billing\Validators\ReportPaymentCommandValidator;
use App\Application\Billing\Validators\RequestUpgradeCommandValidator;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;
use Tests\TestCase;

final class BillingCommandAndValidatorCoverageTest extends TestCase
{
    public function test_report_payment_command_exposes_idempotency_and_payload(): void
    {
        $command = new ReportPaymentCommand(
            tenantId: '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            paymentReference: 'BANK-REF-001',
            amountInCents: 12500,
            currency: 'HNL',
            bank: 'BAC',
            idempotencyKey: 'idem_test_key_report_1',
            proofOfPaymentPath: 'proofs/bank-001.png',
            schemaVersion: 2,
        );

        $this->assertSame('idem_test_key_report_1', $command->getIdempotencyKey());
        $this->assertSame([
            'tenantId' => '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            'paymentReference' => 'BANK-REF-001',
            'amountInCents' => 12500,
            'currency' => 'HNL',
            'bank' => 'BAC',
            'idempotencyKey' => 'idem_test_key_report_1',
            'proofOfPaymentPath' => 'proofs/bank-001.png',
            'schemaVersion' => 2,
        ], $command->toArray());
    }

    public function test_request_upgrade_command_exposes_idempotency_and_payload(): void
    {
        $command = new RequestUpgradeCommand(
            tenantId: '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            newPlanIdentifier: 'pro_monthly',
            currency: 'HNL',
            idempotencyKey: 'idem_test_key_upgrade_1',
            clientIdempotencyKey: 'client-019d80aa',
            schemaVersion: 3,
        );

        $this->assertSame('idem_test_key_upgrade_1', $command->getIdempotencyKey());
        $this->assertSame([
            'tenantId' => '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            'newPlanIdentifier' => 'pro_monthly',
            'currency' => 'HNL',
            'idempotencyKey' => 'idem_test_key_upgrade_1',
            'clientIdempotencyKey' => 'client-019d80aa',
            'schemaVersion' => 3,
        ], $command->toArray());
    }

    public function test_report_payment_validator_validates_and_delegates_to_inner_handler(): void
    {
        $command = new ReportPaymentCommand(
            tenantId: '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            paymentReference: 'BANK-REF-002',
            amountInCents: 9800,
            currency: 'HNL',
            bank: 'Ficohsa',
            idempotencyKey: 'idem_test_key_report_2',
        );

        $validatorFactory = new class implements ValidatorFactoryInterface
        {
            public int $calls = 0;

            /** @var array<string, mixed> */
            public array $lastData = [];

            /** @var array<string, mixed> */
            public array $lastRules = [];

            public function validate(array $data, array $rules): void
            {
                $this->calls++;
                $this->lastData = $data;
                $this->lastRules = $rules;
            }
        };

        $expectedResult = new ReportPaymentResult(
            id: 'pay-1',
            tenantId: $command->tenantId,
            bank: $command->bank,
            referenceNumber: $command->paymentReference,
            amountInCents: $command->amountInCents,
            currency: $command->currency,
            status: 'pending',
            receiptPath: null,
        );

        $inner = new class($expectedResult) implements ReportPaymentHandlerInterface
        {
            public int $calls = 0;

            public function __construct(private readonly ReportPaymentResult $result) {}

            public function handle(ReportPaymentCommand $command): ReportPaymentResult
            {
                $this->calls++;

                return $this->result;
            }
        };

        $decorator = new ReportPaymentCommandValidator($inner, $validatorFactory);
        $result = $decorator->handle($command);

        $this->assertSame(1, $validatorFactory->calls);
        $this->assertArrayHasKey('tenantId', $validatorFactory->lastRules);
        $this->assertArrayHasKey('notes', $validatorFactory->lastRules);
        $this->assertSame($command->toArray(), $validatorFactory->lastData);
        $this->assertSame(1, $inner->calls);
        $this->assertSame($expectedResult, $result);
    }

    public function test_request_upgrade_validator_validates_and_delegates_to_inner_handler(): void
    {
        $command = new RequestUpgradeCommand(
            tenantId: '019d83b3-a0f8-7198-adaa-0d1fed714d29',
            newPlanIdentifier: 'pro_yearly',
            currency: 'HNL',
            idempotencyKey: 'idem_test_key_upgrade_2',
            clientIdempotencyKey: 'client-upg-001',
        );

        $validatorFactory = new class implements ValidatorFactoryInterface
        {
            public int $calls = 0;

            /** @var array<string, mixed> */
            public array $lastData = [];

            /** @var array<string, mixed> */
            public array $lastRules = [];

            public function validate(array $data, array $rules): void
            {
                $this->calls++;
                $this->lastData = $data;
                $this->lastRules = $rules;
            }
        };

        $expectedResult = new RequestUpgradeResult(
            tenantId: $command->tenantId,
            newPlanId: $command->newPlanIdentifier,
            status: 'active',
            validUntil: '2027-01-01',
        );

        $inner = new class($expectedResult) implements RequestUpgradeHandlerInterface
        {
            public int $calls = 0;

            public function __construct(private readonly RequestUpgradeResult $result) {}

            public function handle(RequestUpgradeCommand $command): RequestUpgradeResult
            {
                $this->calls++;

                return $this->result;
            }
        };

        $decorator = new RequestUpgradeCommandValidator($inner, $validatorFactory);
        $result = $decorator->handle($command);

        $this->assertSame(1, $validatorFactory->calls);
        $this->assertArrayHasKey('tenantId', $validatorFactory->lastRules);
        $this->assertArrayHasKey('paymentMethodId', $validatorFactory->lastRules);
        $this->assertSame($command->toArray(), $validatorFactory->lastData);
        $this->assertSame(1, $inner->calls);
        $this->assertSame($expectedResult, $result);
    }

    public function test_report_payment_result_maps_domain_entity(): void
    {
        $entity = new PaymentEntity(
            id: 'pay-entity-001',
            tenantId: 'tenant-001',
            amount: Money::HNL(15000),
            bank: 'Atlantida',
            referenceNumber: 'ATL-REF-001',
            status: PaymentStatus::PENDING,
            proofUrl: 'proofs/atl-001.png',
            createdAt: new DateTimeImmutable('2026-04-18 10:00:00'),
        );

        $result = ReportPaymentResult::fromEntity($entity);

        $this->assertSame('pay-entity-001', $result->id);
        $this->assertSame('tenant-001', $result->tenantId);
        $this->assertSame(15000, $result->amountInCents);
        $this->assertSame('HNL', $result->currency);
        $this->assertSame('pending', $result->status);
        $this->assertSame('proofs/atl-001.png', $result->receiptPath);
    }

    public function test_request_upgrade_result_builds_from_tenant_state(): void
    {
        $fromDate = RequestUpgradeResult::fromTenantState(
            tenantId: 'tenant-001',
            newPlanId: 'growth_yearly',
            endsAt: new DateTimeImmutable('2026-12-31 00:00:00'),
        );

        $fromString = RequestUpgradeResult::fromTenantState(
            tenantId: 'tenant-001',
            newPlanId: 'growth_yearly',
            endsAt: '2027-01-31',
        );

        $this->assertSame('active', $fromDate->status);
        $this->assertSame('2026-12-31', $fromDate->validUntil);
        $this->assertSame('2027-01-31', $fromString->validUntil);
    }
}
