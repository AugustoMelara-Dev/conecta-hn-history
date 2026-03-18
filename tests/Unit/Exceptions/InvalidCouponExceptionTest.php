<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\InvalidCouponException;
use PHPUnit\Framework\TestCase;

/**
 * InvalidCouponExceptionTest - Unit tests for InvalidCouponException
 */
class InvalidCouponExceptionTest extends TestCase
{
    /**
     * Exception should return correct HTTP status code for NOT_FOUND
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_404_for_not_found_error(): void
    {
        $exception = new InvalidCouponException('Coupon not found', 'NOT_FOUND');

        $this->assertEquals(404, $exception->getHttpStatusCode());
        $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
    }

    /**
     * Exception should return correct HTTP status code for EXPIRED
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_410_for_expired_error(): void
    {
        $exception = new InvalidCouponException('Coupon expired', 'EXPIRED');

        $this->assertEquals(410, $exception->getHttpStatusCode());
        $this->assertEquals('EXPIRED', $exception->getErrorCode());
    }

    /**
     * Exception should return correct HTTP status code for DEPLETED
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_410_for_depleted_error(): void
    {
        $exception = new InvalidCouponException('Coupon depleted', 'DEPLETED');

        $this->assertEquals(410, $exception->getHttpStatusCode());
        $this->assertEquals('DEPLETED', $exception->getErrorCode());
    }

    /**
     * Exception should return 410 Gone status for INACTIVE coupon error code
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_410_gone_status_for_inactive_coupon_error_code(): void
    {
        $exception = new InvalidCouponException('Coupon is inactive', 'INACTIVE');

        $this->assertEquals(410, $exception->getHttpStatusCode());
        $this->assertEquals('INACTIVE', $exception->getErrorCode());
    }

    /**
     * Exception should return 422 for default/unknown errors
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_422_for_default_error(): void
    {
        $exception = new InvalidCouponException('Invalid coupon', 'INVALID_MINIMUM');

        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals('INVALID_MINIMUM', $exception->getErrorCode());
    }

    /**
     * toArray should return canonical structure
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function to_array_returns_canonical_structure(): void
    {
        $exception = new InvalidCouponException('Test message', 'TEST_CODE', ['key' => 'value']);

        $array = $exception->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertFalse($array['success']);
        $this->assertEquals('TEST_CODE', $array['error_code']);
        $this->assertEquals('Test message', $array['message']);
        $this->assertArrayHasKey('meta', $array);
        $this->assertIsArray($array['meta']);
    }

    /**
     * Context should include coupon_error and custom data
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function context_includes_coupon_error_and_custom_data(): void
    {
        $exception = new InvalidCouponException('Test', 'EXPIRED', ['remaining' => 50.00]);

        $context = $exception->context();

        $this->assertEquals('EXPIRED', $context['coupon_error']);
        $this->assertEquals(50.00, $context['remaining']);
    }
}
