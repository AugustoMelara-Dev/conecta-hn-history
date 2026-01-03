<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * InvalidCouponException - Custom exception for coupon validation failures.
 *
 * USAGE:
 *   throw new InvalidCouponException('Cupón expirado', 'EXPIRED');
 *   throw new InvalidCouponException('Min no alcanzado', 'MIN_SPEND_NOT_MET', ['remaining' => 50.00]);
 *
 * CATCH:
 *   try {
 *       $coupon = $service->validate($code, $user, $total);
 *   } catch (InvalidCouponException $e) {
 *       return response()->json($e->toArray(), $e->getHttpStatusCode());
 *   }
 */
final class InvalidCouponException extends DomainException
{
    // ═══════════════════════════════════════════════════════════════════════════════
    // ERROR CODES - CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════════════
    public const NOT_FOUND = 'NOT_FOUND';
    public const INACTIVE = 'INACTIVE';
    public const NOT_STARTED = 'NOT_STARTED';
    public const EXPIRED = 'EXPIRED';
    public const DEPLETED = 'DEPLETED';
    public const REQUIRE_LOGIN = 'REQUIRE_LOGIN';
    public const USER_LIMIT_REACHED = 'USER_LIMIT_REACHED';
    public const MIN_SPEND_NOT_MET = 'MIN_SPEND_NOT_MET';
    public const NO_TENANT_CONTEXT = 'NO_TENANT_CONTEXT';

    public function __construct(
        string $message,
        private readonly string $errorCodeValue = self::NOT_FOUND,
        private readonly array $contextData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->validateContextSafety($contextData);
    }

    public function context(): array
    {
        return [
            'coupon_error' => $this->errorCodeValue,
            ...$this->contextData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return match ($this->errorCodeValue) {
            self::NOT_FOUND => 404,
            self::DEPLETED, self::EXPIRED, self::INACTIVE, self::NOT_STARTED => 410, // Gone
            default => 422, // Unprocessable Entity
        };
    }

    public function getErrorCode(): string
    {
        return $this->errorCodeValue;
    }



    // ═══════════════════════════════════════════════════════════════════════════════
    // FACTORY METHODS
    // ═══════════════════════════════════════════════════════════════════════════════

    public static function notFound(string $code = ''): self
    {
        return new self('El código de cupón no existe', self::NOT_FOUND, ['code' => $code]);
    }

    public static function inactive(string $code = ''): self
    {
        return new self('Este cupón no está activo', self::INACTIVE, ['code' => $code]);
    }

    public static function notStarted(string $code = ''): self
    {
        return new self('Este cupón aún no está disponible', self::NOT_STARTED, ['code' => $code]);
    }

    public static function expired(string $code = ''): self
    {
        return new self('Este cupón ha expirado', self::EXPIRED, ['code' => $code]);
    }

    public static function depleted(string $code = ''): self
    {
        return new self('Este cupón ya alcanzó su límite de usos', self::DEPLETED, ['code' => $code]);
    }

    public static function requireLogin(): self
    {
        return new self('Debes iniciar sesión para usar este cupón limitado', self::REQUIRE_LOGIN);
    }

    public static function userLimitReached(string $code = ''): self
    {
        return new self('Ya utilizaste este cupón el máximo de veces permitidas', self::USER_LIMIT_REACHED, ['code' => $code]);
    }

    public static function minSpendNotMet(float $minSpend, float $cartTotal): self
    {
        $remaining = $minSpend - $cartTotal;
        return new self(
            'Agrega L. ' . number_format($remaining, 2) . ' más para usar este cupón',
            self::MIN_SPEND_NOT_MET,
            [
                'min_spend' => $minSpend,
                'cart_total' => $cartTotal,
                'remaining' => $remaining,
            ]
        );
    }

    public static function noTenantContext(): self
    {
        return new self('No se pudo determinar el contexto del negocio', self::NO_TENANT_CONTEXT);
    }
}
