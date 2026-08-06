<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * CsrfExemptions - Lista de rutas exentas de CSRF (webhooks entrantes, APIs externas).
 */
class CsrfExemptions
{
    /**
     * @return array<int, string>
     */
    public function exemptPaths(): array
    {
        return [
            'webhooks/payment',
            'webhooks/*',
            'api/v1/webhooks/*',
            'api/external/*',
            'automation/webhook-receiver',
        ];
    }

    public function isExempt(string $path): bool
    {
        foreach ($this->exemptPaths() as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace('*', '.*', $pattern);
                if (preg_match('#^' . $regex . '#', $path)) {
                    return true;
                }
            } elseif ($path === $pattern) {
                return true;
            }
        }
        return false;
    }
}
