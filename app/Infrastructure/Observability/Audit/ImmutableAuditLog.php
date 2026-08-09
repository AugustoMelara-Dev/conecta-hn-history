<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Audit;

use Illuminate\Support\Facades\DB;

/**
 * ImmutableAuditLog - Audit log inmutable de mutaciones críticas.
 *
 * Append-only log; no permite update ni delete desde PHP.
 */
class ImmutableAuditLog
{
    /**
     * Registra una mutación crítica.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, string $subjectType, ?string $subjectId, ?string $userId, array $context = []): void
    {
        DB::table('audit_log')->insert([
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'context' => json_encode($context),
            'created_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
