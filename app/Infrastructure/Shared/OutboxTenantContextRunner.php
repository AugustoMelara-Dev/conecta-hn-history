<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Closure;
use Illuminate\Support\Str;
use RuntimeException;
use Stringable;

final class OutboxTenantContextRunner
{
    /**
     * @param  array<string, mixed>  $envelope
     */
    public function run(array $envelope, object $event, Closure $next): mixed
    {
        $tenantId = $this->tenantId($envelope, $event);

        return (new TenantQueueMiddleware($tenantId))->handle($event, $next);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function tenantId(array $envelope, object $event): ?string
    {
        $meta = is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [];
        $data = is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
        $candidate = $meta['tenant_id']
            ?? $data['tenantId']
            ?? $data['tenant_id']
            ?? (property_exists($event, 'tenantId') ? $event->tenantId : null);

        if ($candidate === null || $candidate === '') {
            return null;
        }

        if (is_object($candidate) && method_exists($candidate, 'toString')) {
            $candidate = $candidate->toString();
        } elseif ($candidate instanceof Stringable) {
            $candidate = (string) $candidate;
        }

        if (! is_string($candidate) || ! Str::isUuid($candidate)) {
            throw new RuntimeException('Outbox tenant context is invalid.');
        }

        return strtolower($candidate);
    }
}
