<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;
use Throwable;

final class PaymentProcessingException extends DomainException
{
    /**
     * @var array<int, mixed>|null
     */
    public ?array $errorInfo = null;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, ?array $errorInfo = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorInfo = $errorInfo;
    }

    /**
     * @return array{error_info: array<int, mixed>|null}
     */
    public function context(): array
    {
        return ['error_info' => $this->errorInfo];
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_PROCESSING_FAILED';
    }
}
