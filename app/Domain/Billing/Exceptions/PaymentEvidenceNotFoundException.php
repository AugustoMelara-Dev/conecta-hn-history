<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

final class PaymentEvidenceNotFoundException extends RuntimeException
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
