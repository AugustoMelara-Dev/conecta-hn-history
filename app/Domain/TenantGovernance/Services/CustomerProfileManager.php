<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class CustomerProfileManager
{
    /** @var array<string, LoyaltyProgram|null> */
    private array $loyaltyPrograms = [];

    public function __construct(
        private readonly CustomerLoyaltyManager $customerLoyaltyManager,
        private readonly CustomerProfileRepositoryInterface $customerProfileRepository,
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

        return DB::transaction(function () use ($primary, $duplicate, $actorUserId): CustomerProfile {
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

            $this->moveRelatedRecords($primary, $duplicate);

            $this->customerProfileRepository->deleteWithoutScopes((string) $duplicate->getKey());

            $primary->forceFill($mergePayload)->save();

            $this->recalculateProfile($primary);

            $freshPrimary = $primary->fresh();

            if ($actorUserId !== null) {
                $freshPrimary?->forceFill([
                    'crm_notes' => trim((string) collect([
                        $freshPrimary?->crm_notes,
                        'Merge manual ejecutado el '.now()->format('d/m/Y H:i').' por '.$actorUserId.'.',
                    ])->filter()->implode("\n\n")),
                ])->save();
            }

            return $freshPrimary?->fresh() ?? $primary->fresh();
        });
    }

    private function syncSourceRecord(
        Model $source,
        string $tenantId,
        ?string $name,
        ?string $email,
        ?string $phone,
        ?int $previousProfileId = null,
    ): ?CustomerProfile {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = $this->normalizePhone($phone);
        $currentProfileId = $this->normalizeProfileId($source->getAttribute('customer_profile_id'));
        $previousProfileId ??= $currentProfileId;
        $currentProfile = $this->findProfileById($tenantId, $currentProfileId);

        if ($normalizedEmail === null && $normalizedPhone === null) {
            $this->updateSourceLink($source, null);
            $this->syncProfileById($previousProfileId);

            return null;
        }

        $profile = $this->resolveProfile(
            tenantId: $tenantId,
            normalizedEmail: $normalizedEmail,
            normalizedPhone: $normalizedPhone,
            preferredProfile: $this->profileMatchesContact($currentProfile, $normalizedEmail, $normalizedPhone)
                ? $currentProfile
                : null,
        );

        if (! $profile instanceof CustomerProfile) {
            $profile = $this->customerProfileRepository->create([
                    'tenant_id' => $tenantId,
                    'normalized_email' => $normalizedEmail,
                    'normalized_phone' => $normalizedPhone,
                    'name' => $this->sanitizeText($name),
                    'email' => $normalizedEmail,
                    'phone' => $this->sanitizeText($phone),
                    'preferred_channel' => $this->resolvePreferredChannel(null, $normalizedEmail, $normalizedPhone),
                ]);
        }

        $profile->forceFill([
            'normalized_email' => $normalizedEmail ?? $profile->normalized_email,
            'normalized_phone' => $normalizedPhone ?? $profile->normalized_phone,
            'name' => $this->resolveBestName($profile->name, $name),
            'email' => $normalizedEmail ?? $profile->email,
            'phone' => $this->sanitizeText($phone) ?? $profile->phone,
            'preferred_channel' => $this->resolvePreferredChannel(
                $profile->preferred_channel,
                $normalizedEmail ?? $profile->normalized_email,
                $normalizedPhone ?? $profile->normalized_phone,
            ),
        ])->save();

        $this->updateSourceLink($source, (int) $profile->getKey());
        $this->recalculateProfile($profile);

        if ($previousProfileId !== null && $previousProfileId !== (int) $profile->getKey()) {
            $this->syncProfileById($previousProfileId);
        }

        return $profile->fresh();
    }

    private function resolveProfile(
        string $tenantId,
        ?string $normalizedEmail,
        ?string $normalizedPhone,
        ?CustomerProfile $preferredProfile = null,
    ): ?CustomerProfile {
        $matches = $this->customerProfileRepository
            ->findMatchingByContact($tenantId, $normalizedEmail, $normalizedPhone)
            ->sortBy('id')
            ->values();

        if ($preferredProfile instanceof CustomerProfile && ! $matches->contains(
            fn (CustomerProfile $match): bool => (int) $match->getKey() === (int) $preferredProfile->getKey()
        )) {
            $matches->prepend($preferredProfile);
        }

        $matches = $matches
            ->unique(fn (CustomerProfile $match): int => (int) $match->getKey())
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        /** @var CustomerProfile $canonical */
        $canonical = $matches->first();

        if ($matches->count() === 1) {
            return $canonical;
        }

        $duplicateIds = $matches
            ->skip(1)
            ->pluck('id')
            ->filter(static fn (mixed $value): bool => is_numeric($value))
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if ($duplicateIds === []) {
            return $canonical;
        }

        $duplicates = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $duplicateIds)
            ->get();

        $duplicates->each(fn (CustomerProfile $duplicate) => $this->moveRelatedRecords($canonical, $duplicate));

        CustomerProfile::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $duplicateIds)
            ->delete();

        $canonical->forceFill([
            'normalized_email' => $normalizedEmail ?? $canonical->normalized_email,
            'normalized_phone' => $normalizedPhone ?? $canonical->normalized_phone,
        ])->save();

        return $canonical;
    }

    private function moveRelatedRecords(CustomerProfile $primary, CustomerProfile $duplicate): void
    {
        $duplicateId = (int) $duplicate->getKey();
        $primaryId = (int) $primary->getKey();

        Order::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Order::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        Appointment::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Appointment::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        Lead::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Lead::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update(['customer_profile_id' => $primaryId]);

        \App\Models\CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update([
                'customer_profile_id' => $primaryId,
                'assigned_user_id' => $primary->assigned_user_id ?? $duplicate->assigned_user_id,
                'location_id' => $primary->location_id ?? $duplicate->location_id,
            ]);

        \App\Models\CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update(['customer_profile_id' => $primaryId]);

        CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update([
                'customer_profile_id' => $primaryId,
                'assigned_user_id' => $primary->assigned_user_id ?? $duplicate->assigned_user_id,
                'location_id' => $primary->location_id ?? $duplicate->location_id,
            ]);
    }

    private function findProfileById(string $tenantId, ?int $profileId): ?CustomerProfile
    {
        if (! is_int($profileId) || $profileId <= 0) {
            return null;
        }

        $profile = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($profileId);

        return $profile instanceof CustomerProfile ? $profile : null;
    }

    private function profileMatchesContact(
        ?CustomerProfile $profile,
        ?string $normalizedEmail,
        ?string $normalizedPhone,
    ): bool {
        if (! $profile instanceof CustomerProfile) {
            return false;
        }

        $profileEmail = $profile->normalized_email ?? $this->normalizeEmail($profile->email);
        $profilePhone = $profile->normalized_phone ?? $this->normalizePhone($profile->phone);

        return ($normalizedEmail !== null && $normalizedEmail === $profileEmail)
            || ($normalizedPhone !== null && $normalizedPhone === $profilePhone);
    }

    private function updateSourceLink(Model $source, ?int $profileId): void
    {
        $currentProfileId = $this->normalizeProfileId($source->getAttribute('customer_profile_id'));

        if ($currentProfileId === $profileId) {
            return;
        }

        match (true) {
            $source instanceof Order => Order::withoutEvents(function () use ($source, $profileId): void {
                Order::query()
                    ->withoutGlobalScopes()
                    ->whereKey($source->getKey())
                    ->update(['customer_profile_id' => $profileId]);
            }),
            $source instanceof Appointment => Appointment::withoutEvents(function () use ($source, $profileId): void {
                Appointment::query()
                    ->withoutGlobalScopes()
                    ->whereKey($source->getKey())
                    ->update(['customer_profile_id' => $profileId]);
            }),
            $source instanceof Lead => Lead::withoutEvents(function () use ($source, $profileId): void {
                Lead::query()
                    ->withoutGlobalScopes()
                    ->whereKey($source->getKey())
                    ->update(['customer_profile_id' => $profileId]);
            }),
            default => null,
        };

        $source->forceFill(['customer_profile_id' => $profileId]);
    }

    private function recalculateProfile(CustomerProfile $profile): void
    {
        $tenantId = (string) $profile->tenant_id;
        $profileId = (int) $profile->getKey();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->get(['id', 'customer_name', 'customer_email', 'customer_phone', 'total', 'source', 'location_id', 'created_at']);

        $appointments = Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->get(['id', 'customer_name', 'customer_email', 'customer_phone', 'scheduled_at', 'created_at']);

        $leads = Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->get(['id', 'name', 'email', 'phone', 'source', 'created_at']);

        $ordersCount = $orders->count();
        $appointmentsCount = $appointments->count();
        $leadsCount = $leads->count();
        $totalSpent = round((float) $orders->sum('total'), 2);
        $averageTicket = $ordersCount > 0 ? round($totalSpent / $ordersCount, 2) : 0.0;
        $repeatPurchaseCount = max(0, $ordersCount - 1);

        $firstSeenAt = collect([
            $orders->min('created_at'),
            $appointments->min('created_at'),
            $leads->min('created_at'),
        ])->filter()->map(fn (mixed $value): Carbon => Carbon::parse((string) $value))->sort()->first();
        $firstOrder = $orders->sortBy('created_at')->first();
        $firstAppointment = $appointments->sortBy('created_at')->first();
        $firstLead = $leads->sortBy('created_at')->first();

        $lastOrderAt = $this->asCarbon($orders->max('created_at'));
        $lastAppointmentAt = $this->asCarbon($appointments->max('scheduled_at') ?: $appointments->max('created_at'));
        $lastLeadAt = $this->asCarbon($leads->max('created_at'));
        $lastSeenAt = collect([$lastOrderAt, $lastAppointmentAt, $lastLeadAt, $profile->last_contacted_at])
            ->filter(static fn (mixed $value): bool => $value instanceof Carbon)
            ->sort()
            ->last();

        $bestName = collect([
            $profile->name,
            $orders->pluck('customer_name')->filter()->sortByDesc(fn (string $value): int => strlen($value))->first(),
            $appointments->pluck('customer_name')->filter()->sortByDesc(fn (string $value): int => strlen($value))->first(),
            $leads->pluck('name')->filter()->sortByDesc(fn (string $value): int => strlen($value))->first(),
        ])->filter()->first();

        $bestEmail = collect([
            $profile->normalized_email,
            $orders->pluck('customer_email')->map(fn (mixed $value): ?string => $this->normalizeEmail($value))->filter()->first(),
            $appointments->pluck('customer_email')->map(fn (mixed $value): ?string => $this->normalizeEmail($value))->filter()->first(),
            $leads->pluck('email')->map(fn (mixed $value): ?string => $this->normalizeEmail($value))->filter()->first(),
        ])->filter()->first();

        $bestPhone = collect([
            $profile->phone,
            $orders->pluck('customer_phone')->filter()->first(),
            $appointments->pluck('customer_phone')->filter()->first(),
            $leads->pluck('phone')->filter()->first(),
        ])->filter()->first();

        $acquisitionChannel = $this->resolveAcquisitionChannel($firstLead, $firstOrder, $firstAppointment);
        $firstConversionChannel = is_object($firstOrder) ? $this->normalizeChannelValue($firstOrder->source ?? null, 'orders') : null;
        $firstConversionValue = is_object($firstOrder) ? round((float) ($firstOrder->total ?? 0), 2) : 0.0;
        $initialLtv = $this->calculateInitialLtv($orders, $firstOrder);
        $reactivationScore = $this->calculateReactivationScore($lastSeenAt, $ordersCount, $appointmentsCount, $leadsCount, $totalSpent);
        $loyaltyTier = $this->resolveLoyaltyTier($ordersCount, $repeatPurchaseCount, $totalSpent);
        $lastReplyTouchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->whereNotNull('replied_at')
            ->latest('replied_at')
            ->first(['channel', 'replied_at']);
        $bestLocationId = $orders
            ->filter(static fn (Order $order): bool => filled($order->location_id))
            ->sortByDesc('created_at')
            ->pluck('location_id')
            ->map(static fn (mixed $value): ?string => filled($value) ? (string) $value : null)
            ->filter()
            ->first();

        $profile->forceFill([
            'name' => $this->sanitizeText(is_string($bestName) ? $bestName : null),
            'email' => $bestEmail ?? $profile->email,
            'phone' => $this->sanitizeText(is_string($bestPhone) ? $bestPhone : null) ?? $profile->phone,
            'normalized_email' => $bestEmail ?? $profile->normalized_email,
            'normalized_phone' => $this->normalizePhone(is_string($bestPhone) ? $bestPhone : null) ?? $profile->normalized_phone,
            'preferred_channel' => $this->resolvePreferredChannel($profile->preferred_channel, $bestEmail, $this->normalizePhone(is_string($bestPhone) ? $bestPhone : null)),
            'location_id' => $bestLocationId ?? $profile->location_id,
            'acquisition_channel' => $acquisitionChannel,
            'first_conversion_channel' => $firstConversionChannel,
            'first_conversion_value' => $firstConversionValue,
            'initial_ltv' => $initialLtv,
            'orders_count' => $ordersCount,
            'appointments_count' => $appointmentsCount,
            'repeat_purchase_count' => $repeatPurchaseCount,
            'loyalty_tier' => $loyaltyTier,
            'leads_count' => $leadsCount,
            'total_spent' => $totalSpent,
            'average_ticket' => $averageTicket,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $lastSeenAt,
            'last_order_at' => $lastOrderAt,
            'last_appointment_at' => $lastAppointmentAt,
            'last_lead_at' => $lastLeadAt,
            'last_engaged_channel' => $lastReplyTouchpoint?->channel ?? $profile->last_engaged_channel ?? $profile->preferred_channel,
            'last_inbound_reply_at' => $lastReplyTouchpoint?->replied_at ?? $profile->last_inbound_reply_at,
            'lifecycle_stage' => $this->resolveLifecycleStage($ordersCount, $appointmentsCount, $leadsCount, $lastSeenAt),
            'customer_segment' => $this->resolveCustomerSegment($ordersCount, $appointmentsCount, $leadsCount, $totalSpent, $reactivationScore, $lastSeenAt),
            'reactivation_score' => $reactivationScore,
        ])->save();

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

    private function resolveLifecycleStage(
        int $ordersCount,
        int $appointmentsCount,
        int $leadsCount,
        ?Carbon $lastSeenAt,
    ): string {
        if (($ordersCount > 0 || $appointmentsCount > 0) && $lastSeenAt instanceof Carbon && $lastSeenAt->lt(now()->subDays(60))) {
            return 'dormant';
        }

        if ($ordersCount >= 2 || $appointmentsCount >= 2) {
            return 'loyal';
        }

        if ($ordersCount > 0 || $appointmentsCount > 0) {
            return 'active';
        }

        if ($leadsCount > 0) {
            return 'prospect';
        }

        return 'new';
    }

    private function resolveCustomerSegment(
        int $ordersCount,
        int $appointmentsCount,
        int $leadsCount,
        float $totalSpent,
        int $reactivationScore,
        ?Carbon $lastSeenAt,
    ): string {
        if (($ordersCount >= 5 || $totalSpent >= 5000) && $reactivationScore < 65) {
            return 'vip';
        }

        if ($reactivationScore >= 65) {
            return 'reactivation';
        }

        if ($ordersCount >= 2 || $appointmentsCount >= 2) {
            return 'repeat';
        }

        if ($appointmentsCount > 0) {
            return 'booked_service';
        }

        if ($ordersCount > 0) {
            return 'active';
        }

        if ($leadsCount > 0 && $lastSeenAt instanceof Carbon && $lastSeenAt->gte(now()->subDays(14))) {
            return 'prospect';
        }

        return 'new';
    }

    private function resolveAcquisitionChannel(mixed $firstLead, mixed $firstOrder, mixed $firstAppointment): ?string
    {
        $events = collect([
            is_object($firstLead) ? [
                'channel' => $this->normalizeChannelValue($firstLead->source ?? null, 'manual'),
                'date' => $this->asCarbon($firstLead->created_at ?? null),
            ] : null,
            is_object($firstOrder) ? [
                'channel' => $this->normalizeChannelValue($firstOrder->source ?? null, 'orders'),
                'date' => $this->asCarbon($firstOrder->created_at ?? null),
            ] : null,
            is_object($firstAppointment) ? [
                'channel' => 'appointments',
                'date' => $this->asCarbon($firstAppointment->created_at ?? null),
            ] : null,
        ])->filter(static fn (?array $event): bool => is_array($event) && $event['date'] instanceof Carbon)
            ->sortBy(fn (array $event): int => $event['date']->getTimestamp())
            ->values();

        return $events->first()['channel'] ?? null;
    }

    private function calculateInitialLtv(\Illuminate\Support\Collection $orders, mixed $firstOrder): float
    {
        if (! is_object($firstOrder) || ! isset($firstOrder->created_at)) {
            return 0.0;
        }

        $firstOrderAt = $this->asCarbon($firstOrder->created_at);

        if (! $firstOrderAt instanceof Carbon) {
            return 0.0;
        }

        return round((float) $orders
            ->filter(function (Order $order) use ($firstOrderAt): bool {
                $createdAt = $this->asCarbon($order->created_at);

                return $createdAt instanceof Carbon
                    ? $createdAt->lte($firstOrderAt->copy()->addDays(30))
                    : false;
            })
            ->sum('total'), 2);
    }

    private function resolveLoyaltyProgram(string $tenantId): ?LoyaltyProgram
    {
        if (array_key_exists($tenantId, $this->loyaltyPrograms)) {
            return $this->loyaltyPrograms[$tenantId];
        }

        return $this->loyaltyPrograms[$tenantId] = LoyaltyProgram::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    private function resolveLoyaltyTier(int $ordersCount, int $repeatPurchaseCount, float $totalSpent): string
    {
        return match (true) {
            $ordersCount >= 5 || $totalSpent >= 8000 => 'vip',
            $ordersCount >= 3 || $repeatPurchaseCount >= 2 || $totalSpent >= 4000 => 'gold',
            $ordersCount >= 2 || $totalSpent >= 1500 => 'silver',
            default => 'base',
        };
    }

    private function calculateReactivationScore(
        ?Carbon $lastSeenAt,
        int $ordersCount,
        int $appointmentsCount,
        int $leadsCount,
        float $totalSpent,
    ): int {
        if (! $lastSeenAt instanceof Carbon) {
            return 0;
        }

        $days = $lastSeenAt->diffInDays(now());
        $score = 0;

        if ($ordersCount > 0 || $appointmentsCount > 0) {
            $score = match (true) {
                $days >= 90 => 90,
                $days >= 60 => 75,
                $days >= 45 => 65,
                $days >= 30 => 50,
                $days >= 21 => 35,
                default => 0,
            };
        } elseif ($leadsCount > 0) {
            $score = match (true) {
                $days >= 30 => 45,
                $days >= 14 => 25,
                default => 0,
            };
        }

        if ($ordersCount >= 2 || $appointmentsCount >= 2) {
            $score += 10;
        }

        if ($totalSpent >= 5000) {
            $score += 5;
        }

        return max(0, min(100, $score));
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

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = mb_strtolower(trim($value));

        return $email !== '' ? $email : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '504') && strlen($digits) === 11) {
            return substr($digits, 3);
        }

        return $digits;
    }

    private function sanitizeText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean !== '' ? $clean : null;
    }

    private function normalizeChannelValue(mixed $value, string $fallback): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return strtolower(trim($value));
    }

    private function normalizeProfileId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}


