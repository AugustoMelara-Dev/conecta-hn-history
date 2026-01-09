<?php

declare(strict_types=1);

namespace App\Application\Interactions\Results;

use Illuminate\Http\Request;

/**
 * RecordInteractionResult - Pure Immutable DTO for Interaction Recording
 *
 * ZERO SIDE-EFFECTS: No database access, no file I/O.
 * Decouples HTTP layer from Application layer.
 */
final readonly class RecordInteractionResult
{
    public function __construct(
        public int $tenantId,
        public string $type,
        public ?int $productId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referrer,
        public ?string $url,
        public ?array $metadata
    ) {}

    /**
     * Factory method to create DTO from validated request (AJAX tracking).
     */
    public static function fromRequest(Request $request): self
    {
        $validated = $request->validated();

        return new self(
            tenantId: (int) $validated['tenant_id'],
            type: $validated['type'],
            productId: isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referrer: $request->header('referer'),
            url: $request->fullUrl(),
            metadata: null
        );
    }

    /**
     * Factory method for redirect tracking (WhatsApp, Call, Maps).
     */
    public static function forRedirect(
        int $tenantId,
        string $type,
        Request $request,
        ?array $metadata = null
    ): self {
        return new self(
            tenantId: $tenantId,
            type: $type,
            productId: null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referrer: $request->header('referer'),
            url: $request->fullUrl(),
            metadata: $metadata
        );
    }
}
