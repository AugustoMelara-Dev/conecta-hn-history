<?php

declare(strict_types=1);

namespace App\Domain\Exceptions\Payment;

use Exception;

final class InvalidWebhookPayloadException extends Exception
{
    public static function missingTenant(): self
    {
        return new self('Webhook payload does not contain a resolvable Tenant ID.');
    }

    public static function invalidSignature(): self
    {
        return new self('Webhook signature verification failed.');
    }
}
