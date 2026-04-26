<?php

declare(strict_types=1);

namespace App\Listeners\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicConfigAuditInterface;
use Illuminate\Support\Facades\Log;

final class PersistDynamicConfigAudit
{
    public function __construct(
        private readonly DynamicConfigAuditInterface $auditService,
    ) {}

    public function handle(object $event): void
    {
        try {
            $this->auditService->record($event);
        } catch (\Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            Log::warning('dynamic_forms.audit_persist_failed', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
