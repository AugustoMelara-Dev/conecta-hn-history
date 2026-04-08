<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerProfileSnapshotCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(CustomerProfile $profile): array
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
        $lastSeenAt = collect([$lastOrderAt, $lastAppointmentAt, $lastLeadAt, $this->profileDate($profile, 'last_contacted_at')])
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

        $normalizedBestPhone = $this->normalizePhone(is_string($bestPhone) ? $bestPhone : null);
        $acquisitionChannel = $this->resolveAcquisitionChannel($firstLead, $firstOrder, $firstAppointment);
        $firstConversionChannel = is_object($firstOrder) ? $this->normalizeChannelValue($firstOrder->source ?? null, 'orders') : null;
        $firstConversionValue = is_object($firstOrder) ? round((float) ($firstOrder->total ?? 0), 2) : 0.0;
        $initialLtv = $this->calculateInitialLtv($orders, $firstOrder);
        $reactivationScore = $this->calculateReactivationScore($lastSeenAt, $ordersCount, $appointmentsCount, $leadsCount, $totalSpent);
        $loyaltyTier = $this->resolveLoyaltyTier($ordersCount, $repeatPurchaseCount, $totalSpent);
        /** @var CustomerTouchpoint|null $lastReplyTouchpoint */
        $lastReplyTouchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->whereNotNull('replied_at')
            ->latest('replied_at')
            ->first(['channel', 'replied_at']);
        $lastEngagedChannel = $lastReplyTouchpoint instanceof CustomerTouchpoint
            ? $lastReplyTouchpoint->channel
            : ($this->profileString($profile, 'last_engaged_channel') ?? $this->profileString($profile, 'preferred_channel'));
        $lastInboundReplyAt = $lastReplyTouchpoint instanceof CustomerTouchpoint
            ? $lastReplyTouchpoint->replied_at
            : $this->profileDate($profile, 'last_inbound_reply_at');
        $bestLocationId = $orders
            ->filter(static fn (Order $order): bool => filled($order->location_id))
            ->sortByDesc('created_at')
            ->pluck('location_id')
            ->map(static fn (mixed $value): ?string => filled($value) ? (string) $value : null)
            ->filter()
            ->first();

        return [
            'name' => $this->sanitizeText(is_string($bestName) ? $bestName : null),
            'email' => $bestEmail ?? $this->profileString($profile, 'email'),
            'phone' => $this->sanitizeText(is_string($bestPhone) ? $bestPhone : null) ?? $this->profileString($profile, 'phone'),
            'normalized_email' => $bestEmail ?? $this->profileString($profile, 'normalized_email'),
            'normalized_phone' => $normalizedBestPhone ?? $this->profileString($profile, 'normalized_phone'),
            'preferred_channel' => $this->resolvePreferredChannel($this->profileString($profile, 'preferred_channel'), $bestEmail, $normalizedBestPhone),
            'location_id' => $bestLocationId ?? $this->profileString($profile, 'location_id'),
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
            'last_engaged_channel' => $lastEngagedChannel,
            'last_inbound_reply_at' => $lastInboundReplyAt,
            'lifecycle_stage' => $this->resolveLifecycleStage($ordersCount, $appointmentsCount, $leadsCount, $lastSeenAt),
            'customer_segment' => $this->resolveCustomerSegment($ordersCount, $appointmentsCount, $leadsCount, $totalSpent, $reactivationScore, $lastSeenAt),
            'reactivation_score' => $reactivationScore,
        ];
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

    private function calculateInitialLtv(Collection $orders, mixed $firstOrder): float
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

    private function profileString(CustomerProfile $profile, string $attribute): ?string
    {
        if (! array_key_exists($attribute, $profile->getAttributes())) {
            return null;
        }

        $value = $profile->getAttribute($attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function profileDate(CustomerProfile $profile, string $attribute): ?Carbon
    {
        if (! array_key_exists($attribute, $profile->getAttributes())) {
            return null;
        }

        return $this->asCarbon($profile->getAttribute($attribute));
    }
}
