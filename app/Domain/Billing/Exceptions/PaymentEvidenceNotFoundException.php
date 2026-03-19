<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class PaymentEvidenceNotFoundException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $path
    ) {
        parent::__construct($message);
    }

    public static function atPath(string $path): self
    {
        return new self("Payment evidence artifact not found or inaccessible at path: {$path}", $path);
    }

    public static function checkSumFailed(string $path): self
    {
        return new self("Payment evidence artifact at path: {$path} is empty (0 bytes).", $path);
    }

    /**
     * @return array{path: string}
     */
    public function context(): array
    {
        return ['path' => $this->path];
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_EVIDENCE_NOT_FOUND';
    }
}
