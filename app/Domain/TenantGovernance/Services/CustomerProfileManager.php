<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Tenant;

class CustomerProfileManager
{
    public function __construct(
        private readonly CustomerLoyaltyManager $customerLoyaltyManager,
        private readonly CustomerProfileRepositoryInterface $customerProfileRepository,
        private readonly CustomerProfileSnapshotCalculator $snapshotCalculator,
        private readonly CustomerProfileWorkspaceRelinker $workspaceRelinker,
        private readonly CustomerProfileIdentityResolver $identityResolver,
        private readonly TenantContext $tenantContext,
    ) {}

    public function syncTenant(Tenant|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
            })
            ->lazyById(100)
            ->each(fn (Order $order) => $this->syncOrder($order));

        Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
            })
            ->lazyById(100)
            ->each(fn (Appointment $appointment) => $this->syncAppointment($appointment));

        Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('email')->orWhereNotNull('phone');
            })
            ->lazyById(100)
            ->each(fn (Lead $lead) => $this->syncLead($lead));

        $this->pruneEmptyProfiles($tenantId);
    }

    public function syncOrder(Order $order, ?int $previousProfileId = null): ?CustomerProfile
    {
        return $this->syncSourceRecord(
            source: $order,
            tenantId: (string) $order->tenant_id,
            name: $order->customer_name,
            email: $order->customer_email,
            phone: $order->customer_phone,
            previousProfileId: $previousProfileId,
        );
    }

    public function syncAppointment(Appointment $appointment, ?int $previousProfileId = null): ?CustomerProfile
    {
        return $this->syncSourceRecord(
            source: $appointment,
            tenantId: (string) $appointment->tenant_id,
            name: $appointment->customer_name,
            email: $appointment->customer_email,
            phone: $appointment->customer_phone,
            previousProfileId: $previousProfileId,
        );
    }

    public function syncLead(Lead $lead, ?int $previousProfileId = null): ?CustomerProfile
    {
        return $this->syncSourceRecord(
            source: $lead,
            tenantId: (string) $lead->tenant_id,
            name: $lead->name,
            email: $lead->email,
            phone: $lead->phone,
            previousProfileId: $previousProfileId,
        );
    }

    public function syncProfileById(?int $profileId): void
    {
        if (! is_int($profileId) || $profileId <= 0) {
            return;
        }

        $profile = $this->customerProfileRepository->findWithoutScopes($profileId);

        if (! $profile instanceof CustomerProfile) {
            return;
        }

        $this->recalculateProfile($profile);
        $this->deleteIfOrphaned($profile);
    }

    public function mergeProfiles(CustomerProfile $primary, CustomerProfile $duplicate, ?string $actorUserId = null): CustomerProfile
    {
        if ((int) $primary->getKey() === (int) $duplicate->getKey()) {
            return $primary->fresh();
        }

        $initializedTenantContext = false;

        if (! $this->tenantContext->isInitialized()) {
            $this->tenantContext->setTenant((string) $primary->tenant_id);
            $initializedTenantContext = true;
        }

        try {
            return CustomerProfile::query()->getConnection()->transaction(function () use ($primary, $duplicate, $actorUserId): CustomerProfile {
                $primary = $this->customerProfileRepository->findWithoutScopes((string) $primary->getKey())
                    ?? CustomerProfile::query()->withoutGlobalScopes()->findOrFail($primary->getKey());
                $duplicate = $this->customerProfileRepository->findWithoutScopes((string) $duplicate->getKey())
                    ?? CustomerProfile::query()->withoutGlobalScopes()->findOrFail($duplicate->getKey());

                $mergePayload = [
                    'normalized_email' => $primary->normalized_email ?? $duplicate->normalized_email,
                    'normalized_phone' => $primary->normalized_phone ?? $duplicate->normalized_phone,
                    'name' => $this->resolveBestName($primary->name, $duplicate->name),
                    'email' => $primary->email ?? $duplicate->email,
                    'phone' => $primary->phone ?? $duplicate->phone,
                    'preferred_channel' => $this->resolvePreferredChannel(
                        $primary->preferred_channel,
                        $primary->normalized_email ?? $duplicate->normalized_email,
                        $primary->normalized_phone ?? $duplicate->normalized_phone,
                    ),
                    'manual_segment' => $primary->manual_segment ?? $duplicate->manual_segment,
                    'assigned_user_id' => $primary->assigned_user_id ?? $duplicate->assigned_user_id,
                    'location_id' => $primary->location_id ?? $duplicate->location_id,
                    'marketing_opt_in' => (bool) $primary->marketing_opt_in || (bool) $duplicate->marketing_opt_in,
                    'whatsapp_opt_in' => (bool) $primary->whatsapp_opt_in || (bool) $duplicate->whatsapp_opt_in,
                    'email_opt_in' => (bool) $primary->email_opt_in || (bool) $duplicate->email_opt_in,
                    'tags' => collect(array_merge((array) $primary->tags, (array) $duplicate->tags))
                        ->filter(static fn (mixed $tag): bool => is_string($tag) && trim($tag) !== '')
                        ->map(static fn (string $tag): string => trim($tag))
                        ->unique()
                        ->values()
                        ->all(),
                    'crm_notes' => collect([$primary->crm_notes, $duplicate->crm_notes])
                        ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                        ->implode("\n\n"),
                ];

                $this->workspaceRelinker->relink($primary, $duplicate);

                $this->customerProfileRepository->deleteWithoutScopes((string) $duplicate->getKey());

                $primary->forceFill($mergePayload)->save();

                $this->recalculateProfile($primary);

                $freshPrimary = $primary->fresh();

                if ($actorUserId !== null) {
                    $notesTarget = $freshPrimary instanceof CustomerProfile ? $freshPrimary : $primary;

                    $notesTarget->forceFill([
                        'crm_notes' => trim((string) collect([
                            $notesTarget->crm_notes,
                            'Merge manual ejecutado el '.now()->format('d/m/Y H:i').' por '.$actorUserId.'.',
                        ])->filter()->implode("\n\n")),
                    ])->save();
                }

                return ($freshPrimary instanceof CustomerProfile ? $freshPrimary : $primary)->fresh();
            });
        } finally {
            if ($initializedTenantContext) {
                $this->tenantContext->reset();
            }
        }
    }

    private function syncSourceRecord(
        Order|Appointment|Lead $source,
        string $tenantId,
        ?string $name,
        ?string $email,
        ?string $phone,
        ?int $previousProfileId = null,
    ): ?CustomerProfile {
        [$profile, $previousLinkedProfileId] = $this->identityResolver->syncSourceRecord(
            source: $source,
            tenantId: $tenantId,
            name: $name,
            email: $email,
            phone: $phone,
            previousProfileId: $previousProfileId,
        );

        if (! $profile instanceof CustomerProfile) {
            $this->syncProfileById($previousLinkedProfileId);

            return null;
        }

        $this->recalculateProfile($profile);

        if ($previousLinkedProfileId !== null && $previousLinkedProfileId !== (int) $profile->getKey()) {
            $this->syncProfileById($previousLinkedProfileId);
        }

        return $profile->fresh();
    }

    private function recalculateProfile(CustomerProfile $profile): void
    {
        $profile->forceFill($this->snapshotCalculator->calculate($profile))->save();

        $this->customerLoyaltyManager->syncProfile($profile);
    }

    private function deleteIfOrphaned(CustomerProfile $profile): void
    {
        if ($profile->orders_count > 0 || $profile->appointments_count > 0 || $profile->leads_count > 0) {
            return;
        }

        CustomerProfile::query()
            ->withoutGlobalScopes()
            ->whereKey($profile->getKey())
            ->delete();
    }

    private function pruneEmptyProfiles(string $tenantId): void
    {
        CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->each(fn (CustomerProfile $profile) => $this->deleteIfOrphaned($profile));
    }

    private function resolveBestName(?string $existing, ?string $incoming): ?string
    {
        $existing = $this->sanitizeText($existing);
        $incoming = $this->sanitizeText($incoming);

        if ($existing === null) {
            return $incoming;
        }

        if ($incoming === null) {
            return $existing;
        }

        return strlen($incoming) > strlen($existing) ? $incoming : $existing;
    }

    private function resolvePreferredChannel(?string $current, ?string $normalizedEmail, ?string $normalizedPhone): string
    {
        if (is_string($current) && $current !== '') {
            if ($current === 'email' && $normalizedEmail !== null) {
                return $current;
            }

            if (in_array($current, ['whatsapp', 'phone'], true) && $normalizedPhone !== null) {
                return $current;
            }
        }

        if ($normalizedPhone !== null) {
            return 'whatsapp';
        }

        if ($normalizedEmail !== null) {
            return 'email';
        }

        return 'phone';
    }

    private function sanitizeText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean !== '' ? $clean : null;
    }
}
