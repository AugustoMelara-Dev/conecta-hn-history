<?php

declare(strict_types=1);

namespace Tests\Arch;

use App\Contracts\Exceptions\HttpDomainException;
use App\Exceptions\DataIntegrityException;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidBusinessRuleException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\MissingRequiredDataException;
use App\Exceptions\PayloadTooLargeException;
use App\Exceptions\TenantMismatchException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * ExceptionsArchitectureTest - ADR-003 Governance Validation
 *
 * Validates that all domain exceptions comply with the HttpDomainException contract.
 */
class ExceptionsArchitectureTest extends TestCase
{
    /**
     * All concrete exception classes that must implement HttpDomainException.
     */
    private const EXCEPTION_CLASSES = [
        DataIntegrityException::class,
        InvalidBusinessRuleException::class,
        InvalidCouponException::class,
        InvalidOrderTransitionException::class,
        MissingRequiredDataException::class,
        PayloadTooLargeException::class,
        TenantMismatchException::class,
    ];

    /**
     * @test
     * All exceptions must implement HttpDomainException interface
     */
    public function all_exceptions_implement_http_domain_exception_interface(): void
    {
        foreach (self::EXCEPTION_CLASSES as $class) {
            $this->assertTrue(
                is_subclass_of($class, HttpDomainException::class),
                "{$class} must implement HttpDomainException interface"
            );
        }
    }

    /**
     * @test
     * All exceptions must have getHttpStatusCode method
     */
    public function all_exceptions_have_get_http_status_code_method(): void
    {
        foreach (self::EXCEPTION_CLASSES as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue(
                $reflection->hasMethod('getHttpStatusCode'),
                "{$class} must have getHttpStatusCode method"
            );

            $method = $reflection->getMethod('getHttpStatusCode');
            $this->assertTrue(
                $method->isPublic(),
                "{$class}::getHttpStatusCode must be public"
            );
        }
    }

    /**
     * @test
     * All exceptions must have getErrorCode method
     */
    public function all_exceptions_have_get_error_code_method(): void
    {
        foreach (self::EXCEPTION_CLASSES as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue(
                $reflection->hasMethod('getErrorCode'),
                "{$class} must have getErrorCode method"
            );

            $method = $reflection->getMethod('getErrorCode');
            $this->assertTrue(
                $method->isPublic(),
                "{$class}::getErrorCode must be public"
            );
        }
    }

    /**
     * @test
     * All exceptions' toArray() must return canonical structure with 'success' => false
     */
    public function all_exceptions_to_array_returns_canonical_structure(): void
    {
        $testCases = [
            new DataIntegrityException('Test message'),
            new InvalidBusinessRuleException('Test message'),
            new InvalidCouponException('Test message'),
            new InvalidOrderTransitionException('pending', 'shipped'),
            new MissingRequiredDataException('Test message'),
            new PayloadTooLargeException('Test message'),
            new TenantMismatchException('Test message'),
        ];

        foreach ($testCases as $exception) {
            $array = $exception->toArray();

            $this->assertArrayHasKey('success', $array, get_class($exception) . ' toArray must have "success" key');
            $this->assertFalse($array['success'], get_class($exception) . ' toArray["success"] must be false');

            $this->assertArrayHasKey('error_code', $array, get_class($exception) . ' toArray must have "error_code" key');
            $this->assertIsString($array['error_code'], get_class($exception) . ' error_code must be string');

            $this->assertArrayHasKey('message', $array, get_class($exception) . ' toArray must have "message" key');
            $this->assertIsString($array['message'], get_class($exception) . ' message must be string');

            $this->assertArrayHasKey('meta', $array, get_class($exception) . ' toArray must have "meta" key');
            $this->assertIsArray($array['meta'], get_class($exception) . ' meta must be array');
        }
    }

    /**
     * @test
     * Error codes must be in SCREAMING_SNAKE_CASE format
     */
    public function error_codes_are_screaming_snake_case(): void
    {
        $testCases = [
            ['exception' => new DataIntegrityException('Test'), 'expected' => 'DATA_INTEGRITY_VIOLATION'],
            ['exception' => new InvalidBusinessRuleException('Test'), 'expected' => 'BUSINESS_RULE_VIOLATION'],
            ['exception' => new InvalidOrderTransitionException('a', 'b'), 'expected' => 'INVALID_ORDER_TRANSITION'],
            ['exception' => new MissingRequiredDataException('Test'), 'expected' => 'MISSING_REQUIRED_DATA'],
            ['exception' => new PayloadTooLargeException('Test'), 'expected' => 'PAYLOAD_TOO_LARGE'],
            ['exception' => new TenantMismatchException('Test'), 'expected' => 'TENANT_MISMATCH'],
        ];

        foreach ($testCases as $case) {
            $exception = $case['exception'];
            $expectedCode = $case['expected'];

            $this->assertEquals(
                $expectedCode,
                $exception->getErrorCode(),
                get_class($exception) . ' must return correct error code'
            );

            // Validate SCREAMING_SNAKE_CASE format
            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/',
                $exception->getErrorCode(),
                get_class($exception) . ' error code must be SCREAMING_SNAKE_CASE'
            );
        }
    }

    /**
     * @test
     * HTTP status codes must be valid
     */
    public function http_status_codes_are_valid(): void
    {
        $testCases = [
            DataIntegrityException::class => 409,
            InvalidBusinessRuleException::class => 422,
            InvalidOrderTransitionException::class => 422,
            MissingRequiredDataException::class => 422,
            PayloadTooLargeException::class => 413,
            TenantMismatchException::class => 403,
        ];

        foreach ($testCases as $class => $expectedCode) {
            $exception = $this->createExceptionInstance($class);
            $this->assertEquals(
                $expectedCode,
                $exception->getHttpStatusCode(),
                "{$class} must return HTTP status code {$expectedCode}"
            );
        }
    }

    /**
     * @test
     * DomainException base class must be abstract
     */
    public function domain_exception_is_abstract(): void
    {
        $reflection = new ReflectionClass(DomainException::class);
        $this->assertTrue($reflection->isAbstract(), 'DomainException must be abstract');
    }

    /**
     * @test
     * All concrete exceptions must extend DomainException
     */
    public function all_exceptions_extend_domain_exception(): void
    {
        foreach (self::EXCEPTION_CLASSES as $class) {
            $this->assertTrue(
                is_subclass_of($class, DomainException::class),
                "{$class} must extend DomainException"
            );
        }
    }

    /**
     * @test
     * No conflicting getStatusCode method should exist - only getHttpStatusCode is allowed
     */
    public function no_conflicting_get_status_code_method(): void
    {
        foreach (self::EXCEPTION_CLASSES as $class) {
            $reflection = new ReflectionClass($class);

            // Check if class has its own getStatusCode (not inherited from Exception)
            if ($reflection->hasMethod('getStatusCode')) {
                $method = $reflection->getMethod('getStatusCode');
                // If declared in this class (not parent), it's a violation
                $this->assertNotEquals(
                    $class,
                    $method->getDeclaringClass()->getName(),
                    "{$class} should not have getStatusCode() - use getHttpStatusCode() instead for consistency"
                );
            }
        }
    }

    /**
     * @test
     * Security: Exceptions must throw LogicException if context contains forbidden PII keys
     */
    public function it_prohibits_sensitive_keys_in_context(): void
    {
        $forbiddenKeys = [
            'password', 'api_key', 'token', 'secret', 'credit_card', 'ssn', 'authorization'
        ];

        foreach ($forbiddenKeys as $key) {
            try {
                // Create a concrete implementation of abstract DomainException for testing
                new class("Security Breach Test", [$key => 'leak']) extends DomainException {
                    public function __construct(string $message, array $context) {
                        parent::__construct($message);
                        $this->validateContextSafety($context);
                    }
                    public function context(): array { return []; }
                    public function getErrorCode(): string { return 'TEST'; }
                };
                $this->fail("Should have thrown LogicException for forbidden key: {$key}");
            } catch (\LogicException $e) {
                $this->assertStringContainsString("Exception context cannot contain '{$key}'", $e->getMessage());
            }
        }
    }

    /**
     * @test
     * Security: Exceptions must throw LogicException if context values contain sensitive patterns
     */
    public function it_prohibits_sensitive_values_in_context(): void
    {
        $sensitiveValues = [
            'password=secret',
            'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
            'api_key: 12345',
        ];

        foreach ($sensitiveValues as $value) {
            try {
                new class("Security Pattern Test", ['safe_key' => $value]) extends DomainException {
                    public function __construct(string $message, array $context) {
                        parent::__construct($message);
                        $this->validateContextSafety($context);
                    }
                    public function context(): array { return []; }
                    public function getErrorCode(): string { return 'TEST'; }
                };
                $this->fail("Should have thrown LogicException for sensitive value: {$value}");
            } catch (\LogicException $e) {
                $this->assertStringContainsString('sensitive data pattern', $e->getMessage());
            }
        }
    }

    private function createExceptionInstance(string $class): DomainException
    {
        return match ($class) {
            InvalidOrderTransitionException::class => new InvalidOrderTransitionException('a', 'b'),
            default => new $class('Test message'),
        };
    }
}
