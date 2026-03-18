<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Domain\Shared\ValueObjects\TenantId;
use App\Exceptions\InvalidBusinessRuleException;
use App\Exports\Concerns\SanitizesOutput;
use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use Tests\TestCase;

/**
 * SalesExportUnitTest - Architecture, Security and Compliance Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  These tests verify the ARCHITECTURE without database dependencies      ║
 * ║  Coverage:                                                               ║
 * ║    - Type safety (TenantId, SalesExportCriteria)                        ║
 * ║    - Security (SanitizesOutput, CSV injection prevention)               ║
 * ║    - DoS prevention (date range limits)                                 ║
 * ║    - Input validation (status values)                                   ║
 * ║    - Factory pattern (no event dispatch in constructor)                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class SalesExportUnitTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // Constructor Type Safety Tests
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * TenantId Value Object is required (not primitive int)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_requires_tenant_id_value_object(): void
    {
        $reflection = new \ReflectionClass(SalesExport::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $tenantIdParam = $parameters[0];

        $this->assertEquals('tenantId', $tenantIdParam->getName());
        $this->assertEquals(TenantId::class, $tenantIdParam->getType()->getName());
    }

    /**
     * SalesExportCriteria is required (not loose arrays)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_requires_criteria_object(): void
    {
        $reflection = new \ReflectionClass(SalesExport::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $criteriaParam = $parameters[1];

        $this->assertEquals('criteria', $criteriaParam->getName());
        $this->assertEquals(SalesExportCriteria::class, $criteriaParam->getType()->getName());
    }

    /**
     * Constructor is private - must use factory method
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_is_private_enforcing_factory_pattern(): void
    {
        $reflection = new \ReflectionClass(SalesExport::class);
        $constructor = $reflection->getConstructor();

        $this->assertTrue(
            $constructor->isPrivate(),
            'Constructor must be private to enforce factory pattern (event dispatch AFTER construction)'
        );
    }

    /**
     * SalesExport has static create() factory method
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function class_has_create_factory_method(): void
    {
        $this->assertTrue(
            method_exists(SalesExport::class, 'create'),
            'SalesExport must have static create() factory method'
        );

        $reflection = new \ReflectionMethod(SalesExport::class, 'create');
        $this->assertTrue($reflection->isStatic(), 'create() must be static');
        $this->assertTrue($reflection->isPublic(), 'create() must be public');
    }

    /**
     * SalesExport uses SanitizesOutput trait for CSV injection prevention
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function class_uses_sanitizes_output_trait(): void
    {
        $traits = class_uses(SalesExport::class);

        $this->assertArrayHasKey(
            SanitizesOutput::class,
            $traits,
            'SalesExport must use SanitizesOutput trait for CSV injection prevention'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DoS Prevention Tests (Date Range Validation)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SalesExportCriteria validates date range does not exceed 365 days
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_rejects_date_range_exceeding_365_days(): void
    {
        $this->expectException(InvalidBusinessRuleException::class);
        $this->expectExceptionMessage('El rango de fechas no puede exceder 365 días');

        SalesExportCriteria::create(
            startDate: '2023-01-01',
            endDate: '2025-01-01', // 731 days - exceeds limit
        );
    }

    /**
     * SalesExportCriteria accepts valid date range
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_accepts_valid_date_range(): void
    {
        $criteria = SalesExportCriteria::create(
            startDate: '2025-01-01',
            endDate: '2025-06-01', // ~150 days - within limit
        );

        $this->assertInstanceOf(SalesExportCriteria::class, $criteria);
        $this->assertNotEmpty($criteria->toHash());
    }

    /**
     * SalesExportCriteria always has dates (DoS prevention)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_always_has_dates_when_created_without_parameters(): void
    {
        $criteria = SalesExportCriteria::create();

        // Should have default 30-day range
        $this->assertNotNull($criteria->startDate());
        $this->assertNotNull($criteria->endDate());
        $this->assertLessThanOrEqual(31, $criteria->dateRangeInDays()); // 30 days + 1 for inclusive
    }

    /**
     * SalesExportCriteria::forLastDays rejects invalid days
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_rejects_invalid_day_count(): void
    {
        $this->expectException(InvalidBusinessRuleException::class);

        SalesExportCriteria::forLastDays(0);
    }

    /**
     * SalesExportCriteria::forLastDays rejects days over 365
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_rejects_days_over_365(): void
    {
        $this->expectException(InvalidBusinessRuleException::class);

        SalesExportCriteria::forLastDays(400);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Status Validation Tests
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SalesExportCriteria rejects invalid status values
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_rejects_invalid_status(): void
    {
        $this->expectException(InvalidBusinessRuleException::class);
        $this->expectExceptionMessage('Estado inválido');

        SalesExportCriteria::create(
            startDate: '2025-01-01',
            endDate: '2025-01-31',
            status: 'INVALID_STATUS_THAT_DOES_NOT_EXIST'
        );
    }

    /**
     * SalesExportCriteria accepts all valid status values
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_accepts_valid_statuses(): void
    {
        $validStatuses = ['pending', 'confirmed', 'processing', 'ready', 'delivered', 'cancelled'];

        foreach ($validStatuses as $status) {
            $criteria = SalesExportCriteria::create(
                startDate: '2025-01-01',
                endDate: '2025-01-31',
                status: $status
            );
            $this->assertEquals($status, $criteria->status());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CSV Injection Prevention Tests
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SanitizesOutput trait escapes formula injection characters
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitizes_output_escapes_formula_characters(): void
    {
        $export = new class
        {
            use SanitizesOutput;

            public function testSanitize(mixed $input): mixed
            {
                return $this->sanitize($input);
            }
        };

        // Test dangerous prefixes that always get escaped
        $this->assertEquals("'=CMD|'/C calc'!A1", $export->testSanitize("=CMD|'/C calc'!A1"));
        $this->assertEquals("'@{malicious}", $export->testSanitize('@{malicious}'));

        // Test that formulas starting with - are escaped
        $this->assertEquals("'-SUM(A1:A10)", $export->testSanitize('-SUM(A1:A10)'));

        // Test safe strings pass through unchanged
        $this->assertEquals('Normal text', $export->testSanitize('Normal text'));
        $this->assertEquals('100.00', $export->testSanitize('100.00'));
    }

    /**
     * SanitizesOutput preserves legitimate negative numbers
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function sanitizes_output_preserves_negative_numbers(): void
    {
        $export = new class
        {
            use SanitizesOutput;

            public function testSanitize(mixed $input): mixed
            {
                return $this->sanitize($input);
            }
        };

        // CRITICAL: Negative numbers must NOT be escaped
        $this->assertEquals('-150.00', $export->testSanitize('-150.00'));
        $this->assertEquals('-1234567890', $export->testSanitize('-1234567890'));
        $this->assertEquals('+42', $export->testSanitize('+42'));
        $this->assertEquals('-0.50', $export->testSanitize('-0.50'));

        // Currency-like negative numbers should also be preserved
        $this->assertEquals('-$150.00', $export->testSanitize('-$150.00'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TenantId Validation Tests
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * TenantId rejects invalid UUIDs
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_id_rejects_invalid_uuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantId::fromString('invalid-uuid-string');
    }

    /**
     * TenantId accepts valid UUID
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_id_accepts_valid_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        $this->assertEquals($uuid, $tenantId->toString());
        $this->assertEquals($uuid, $tenantId->value);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Date Range Order Validation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SalesExportCriteria rejects start date after end date
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function criteria_rejects_start_after_end(): void
    {
        $this->expectException(InvalidBusinessRuleException::class);
        $this->expectExceptionMessage('La fecha de inicio debe ser anterior o igual a la fecha de fin');

        SalesExportCriteria::create(
            startDate: '2025-12-31',
            endDate: '2025-01-01', // End before start
        );
    }
}