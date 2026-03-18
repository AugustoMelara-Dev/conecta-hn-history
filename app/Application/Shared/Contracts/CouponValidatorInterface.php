<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Models\Coupon;

interface CouponValidatorInterface
{
    public function validate(string $code, UserEntity $user, float $cartTotal, TenantEntity $tenant): Coupon;

    public function redeem(Coupon $coupon, UserEntity $user, ?int $orderId = null): void;

    public function linkToOrder(Coupon $coupon, UserEntity $user, int $orderId): void;
}
