<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Domain\Discovery\BuyerActionRequest;
use App\Domain\Discovery\PreparedBuyerAction;
use App\Infrastructure\Discovery\CanonicalAvailabilityInspector;
use App\Infrastructure\Discovery\DiscoveryProjectionStore;

final class BuyerActionPreparationService
{
    public function __construct(
        private readonly DiscoveryProjectionStore $store,
        private readonly CanonicalAvailabilityInspector $availability,
    ) {}

    public function prepare(BuyerActionRequest $request): PreparedBuyerAction
    {
        $projection = $this->store->findProjection($request->projectionId);

        if ($projection === null) {
            return new PreparedBuyerAction(
                status: 'rejected',
                reasonCode: 'not_found',
                message: 'The published offer could not be found.',
                payload: [],
                lineage: [],
                requiresConfirmation: false,
                executionAllowed: false,
            );
        }

        if ((bool) $projection->prohibited_offer || (string) $projection->moderation_status === 'rejected') {
            return $this->rejected($projection, $request, 'prohibited', 'This offer cannot be prepared for buyer action.');
        }

        if ($projection->source_type === 'service') {
            $availabilityStatus = $this->availability->statusForDate(
                tenantId: (string) $projection->source_tenant_id,
                locationId: $projection->location_id !== null ? (string) $projection->location_id : null,
                serviceId: (int) $projection->source_record_id,
                localDate: $request->desiredLocalDate,
            );

            if (! in_array($availabilityStatus, ['available', 'limited'], true)) {
                return $this->rejected($projection, $request, 'unavailable', 'This service is not currently available for the requested date.');
            }

            $payload = [
                'projection_id' => (string) $projection->id,
                'action_type' => $request->actionType,
                'source_type' => 'service',
                'source_record_id' => (int) $projection->source_record_id,
                'offer_name' => (string) $projection->offer_name,
                'currency_code' => (string) $projection->currency_code,
                'quoted_total_minor' => (int) $projection->price_minor * max(1, $request->quantity),
                'desired_local_date' => $request->desiredLocalDate,
                'timezone_name' => (string) $projection->timezone_name,
                'location_id' => $projection->location_id !== null ? (string) $projection->location_id : null,
                'confirmation_required' => true,
            ];

            return $this->prepared($projection, $request, $availabilityStatus, $payload);
        }

        if (! in_array((string) $projection->availability_status, ['available', 'backorder', 'limited'], true)) {
            return $this->rejected($projection, $request, 'unavailable', 'This product is not currently available.');
        }

        $payload = [
            'projection_id' => (string) $projection->id,
            'action_type' => $request->actionType,
            'source_type' => 'product',
            'source_record_id' => (int) $projection->source_record_id,
            'offer_name' => (string) $projection->offer_name,
            'quantity' => max(1, $request->quantity),
            'currency_code' => (string) $projection->currency_code,
            'unit_price_minor' => (int) $projection->price_minor,
            'quoted_total_minor' => (int) $projection->price_minor * max(1, $request->quantity),
            'confirmation_required' => true,
        ];

        return $this->prepared($projection, $request, (string) $projection->availability_status, $payload);
    }

    /** @param object $projection @param array<string, mixed> $payload */
    private function prepared(object $projection, BuyerActionRequest $request, string $availabilityStatus, array $payload): PreparedBuyerAction
    {
        $lineage = [
            'projection_id' => (string) $projection->id,
            'source_type' => (string) $projection->source_type,
            'source_record_id' => (int) $projection->source_record_id,
        ];

        $preparationId = $this->store->recordActionPreparation(
            projectionId: (string) $projection->id,
            sourceTenantId: (string) $projection->source_tenant_id,
            actionType: $request->actionType,
            status: 'requires_confirmation',
            reasonCode: null,
            requestedLocalDate: $request->desiredLocalDate,
            currencyCode: $projection->currency_code !== null ? (string) $projection->currency_code : null,
            quotedTotalMinor: (int) ($payload['quoted_total_minor'] ?? 0),
            availabilityStatus: $availabilityStatus,
            confirmationRequired: true,
            payload: $payload,
            lineage: $lineage,
        );

        $lineage['preparation_id'] = $preparationId;

        return new PreparedBuyerAction(
            status: 'requires_confirmation',
            reasonCode: null,
            message: 'Preparation is ready, but a later confirmed writer must execute any economic action.',
            payload: $payload,
            lineage: $lineage,
            requiresConfirmation: true,
            executionAllowed: false,
        );
    }

    private function rejected(object $projection, BuyerActionRequest $request, string $reasonCode, string $message): PreparedBuyerAction
    {
        $lineage = [
            'projection_id' => (string) $projection->id,
            'source_type' => (string) $projection->source_type,
            'source_record_id' => (int) $projection->source_record_id,
        ];

        $preparationId = $this->store->recordActionPreparation(
            projectionId: (string) $projection->id,
            sourceTenantId: (string) $projection->source_tenant_id,
            actionType: $request->actionType,
            status: 'rejected',
            reasonCode: $reasonCode,
            requestedLocalDate: $request->desiredLocalDate,
            currencyCode: $projection->currency_code !== null ? (string) $projection->currency_code : null,
            quotedTotalMinor: null,
            availabilityStatus: (string) $projection->availability_status,
            confirmationRequired: false,
            payload: [],
            lineage: $lineage,
        );

        $lineage['preparation_id'] = $preparationId;

        return new PreparedBuyerAction(
            status: 'rejected',
            reasonCode: $reasonCode,
            message: $message,
            payload: [],
            lineage: $lineage,
            requiresConfirmation: false,
            executionAllowed: false,
        );
    }
}
