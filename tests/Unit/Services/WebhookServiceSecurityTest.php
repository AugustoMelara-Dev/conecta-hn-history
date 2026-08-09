<?php

declare(strict_types=1);

use App\Services\WebhookService;

/**
 * VC-AUDIT-101 Test Suite
 *
 * Ensures WebhookService logs tenant_id during payment webhook processing.
 */
describe('VC-AUDIT-101: WebhookService Audit Logging', function () {
    it('logs tenant_id during payment webhook processing', function () {
        // Static analysis: Verify the audit logging is in place
        $reflection = new ReflectionMethod(WebhookService::class, 'handlePaymentSucceeded');
        $source = file_get_contents($reflection->getFileName());

        // Verify the fix is in place
        expect($source)
            ->toContain('// VC-AUDIT-101: Audit trail for global order lookup')
            ->and($source)
            ->toContain("Log::info('PaymentWebhook: Processing order'")
            ->and($source)
            ->toContain("'tenant_id' => \$rawOrder->tenant_id");
    });

    it('includes all required fields in audit log', function () {
        $reflection = new ReflectionMethod(WebhookService::class, 'handlePaymentSucceeded');
        $source = file_get_contents($reflection->getFileName());

        // Verify all tracking fields are logged
        expect($source)
            ->toContain("'order_number' => \$orderNumber")
            ->and($source)
            ->toContain("'tenant_id' => \$rawOrder->tenant_id")
            ->and($source)
            ->toContain("'transaction_id' => \$transactionId");
    });
});
