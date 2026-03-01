<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;
use Throwable;

final class PaymentProcessingException extends DomainException
{
    /**
     * @var array<int, mixed>|null
     */
    public ?array $errorInfo = null;

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, ?array $errorInfo = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorInfo = $errorInfo;
    }
}
