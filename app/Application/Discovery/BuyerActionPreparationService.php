<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Domain\Discovery\BuyerActionRequest;
use App\Domain\Discovery\PreparedBuyerAction;
use App\Infrastructure\Discovery\CanonicalAvailabilityInspector;
use App\Infrastructure\Discovery\CanonicalPublishedOfferSource;
use App\Infrastructure\Discovery\DiscoveryProjectionStore;

final class BuyerActionPreparationService
{
    public function __construct(
        private readonly DiscoveryProjectionStore $store,
        private readonly CanonicalAvailabilityInspector $availability,
        private readonly CanonicalPublishedOfferSource $source,
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

        if (! (bool) $projection->is_published
            || ! (bool) $projection->is_current
            || ! (bool) $projection->is_eligible) {
            $reasonCode = in_array((string) $projection->availability_status, ['unknown', 'unavailable'], true)
                ? 'unavailable'
                : 'not_eligible';

            return $this->rejected($projection, $request, $reasonCode, 'This published offer is no longer eligible for buyer action.');
        }

        $canonicalReason = $this->canonicalRejectionReason($projection);
        if ($canonicalReason !== null) {
            return $this->rejected($projection, $request, $canonicalReason, 'The published offer changed and must be revalidated before buyer action.');
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

    private function canonicalRejectionReason(object $projection): ?string
    {
        $candidate = $this->source->candidateForProjectionKey((string) $projection->projection_key);
        if ($candidate === null) {
            return $this->source->tenantCanPublish((string) $projection->source_tenant_id)
                ? 'canonical_source_missing'
                : 'tenant_changed';
        }

        if ((string) $candidate['source_tenant_id'] !== (string) $projection->source_tenant_id) {
            return 'tenant_changed';
        }

        if ((string) $candidate['source_type'] !== (string) $projection->source_type
            || (int) $candidate['source_record_id'] !== (int) $projection->source_record_id) {
            return 'canonical_source_changed';
        }

        foreach ([
            'tenant_market_id',
            'location_id',
            'country_code',
            'locale_code',
            'currency_code',
            'timezone_name',
        ] as $marketField) {
            if (($candidate[$marketField] ?? null) !== ($projection->{$marketField} ?? null)) {
                return 'market_changed';
            }
        }

        if ($candidate['source_updated_at']->toDateTimeString() !== (string) $projection->source_updated_at) {
            return 'canonical_source_changed';
        }

        foreach (['offer_name', 'price_minor', 'availability_status'] as $canonicalField) {
            if ((string) ($candidate[$canonicalField] ?? '') !== (string) ($projection->{$canonicalField} ?? '')) {
                return 'canonical_source_changed';
            }
        }

        $expectedEvidence = array_column($candidate['evidence'], 'evidence_key');
        $storedEvidence = $this->store->evidenceForProjection((string) $projection->id);
        $storedEvidenceKeys = array_column($storedEvidence, 'evidence_key');

        sort($expectedEvidence);
        sort($storedEvidenceKeys);

        if ($expectedEvidence !== $storedEvidenceKeys) {
            return 'evidence_changed';
        }

        foreach ($storedEvidence as $evidence) {
            if ((string) ($evidence['source_tenant_id'] ?? '') !== (string) $projection->source_tenant_id
                || ! (bool) $evidence['is_current']
                || (string) $evidence['visibility'] !== 'public') {
                return 'evidence_changed';
            }
        }

        return null;
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
