<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class PaymentEvidenceNotFoundException extends DomainException
{
    public static function atPath(string $path): self
    {
        return new self("Payment evidence artifact not found or inaccessible at path: {$path}");
    }

    public static function checkSumFailed(string $path): self
    {
        return new self("Payment evidence artifact at path: {$path} is empty (0 bytes).");
    }
}
