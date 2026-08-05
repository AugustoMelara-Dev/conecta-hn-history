<?php

declare(strict_types=1);

namespace App\Infrastructure\Availability;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: all unscoped reads include the explicit tenant supplied by the application boundary.

use App\Application\Availability\AvailabilityDecision;
use App\Application\Availability\AvailabilityQuery;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceProvider;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentAvailabilityQuery implements AvailabilityQuery
{
    public function slots(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
        CarbonImmutable $localDate,
    ): array {
        [$service, , $location] = $this->context(
            $tenantId,
            $serviceId,
            $providerId,
            $locationId,
        );
        $timezone = (string) $location->timezone_name;
        $date = $localDate->format('Y-m-d');
        $ranges = $this->ranges(
            $tenantId,
            $serviceId,
            $providerId,
            $locationId,
            $date,
        );
        if ($ranges === []) {
            return [];
        }

        $duration = (int) $service->duration_minutes;
        $interval = max(15, min($duration, 60));
        $slots = [];
        foreach ($ranges as $range) {
            $rangeStart = $this->minutes((string) $range->starts_at_local);
            $rangeEnd = $this->minutes((string) $range->ends_at_local);
            for ($minute = $rangeStart; $minute + $duration <= $rangeEnd; $minute += $interval) {
                $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
                $startsAtUtc = $this->wallToUtc($date, $time, $timezone, false);
                if (! $startsAtUtc instanceof CarbonImmutable) {
                    continue;
                }
                $endsAtUtc = $startsAtUtc->addMinutes($duration);
                $endLocal = $endsAtUtc->setTimezone($timezone);
                if ($endLocal->format('Y-m-d') !== $date
                    || $this->minutes($endLocal->format('H:i')) > $rangeEnd
                    || $this->hasOverlap($tenantId, $providerId, $startsAtUtc, $endsAtUtc)) {
                    continue;
                }
                $slots[$startsAtUtc->toIso8601String()] = [
                    'time' => $time,
                    'display' => $startsAtUtc->setTimezone($timezone)->format('h:i A'),
                    'starts_at_utc' => $startsAtUtc->toIso8601String(),
                    'ends_at_utc' => $endsAtUtc->toIso8601String(),
                    'timezone' => $timezone,
                ];
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    public function assertAvailable(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): AvailabilityDecision {
        [$service, , $location] = $this->context(
            $tenantId,
            $serviceId,
            $providerId,
            $locationId,
        );
        $timezone = (string) $location->timezone_name;
        $startsAtUtc = $startsAtUtc->utc();
        $endsAtUtc = $endsAtUtc->utc();
        if ($endsAtUtc <= $startsAtUtc
            || (int) round($startsAtUtc->diffInMinutes($endsAtUtc)) !== (int) $service->duration_minutes) {
            return new AvailabilityDecision(
                false,
                'duration_mismatch',
                $startsAtUtc,
                $endsAtUtc,
                $timezone,
            );
        }

        $localStart = $startsAtUtc->setTimezone($timezone);
        $date = $localStart->format('Y-m-d');
        $time = $localStart->format('H:i');
        $ranges = $this->ranges(
            $tenantId,
            $serviceId,
            $providerId,
            $locationId,
            $date,
        );
        $insideRule = false;
        foreach ($ranges as $range) {
            if ($time < $range->starts_at_local || $time >= $range->ends_at_local) {
                continue;
            }
            $rangeEndUtc = $this->wallToUtc(
                $date,
                (string) $range->ends_at_local,
                $timezone,
                true,
            );
            if ($rangeEndUtc instanceof CarbonImmutable && $endsAtUtc <= $rangeEndUtc) {
                $insideRule = true;
                break;
            }
        }
        if (! $insideRule) {
            return new AvailabilityDecision(
                false,
                'outside_availability',
                $startsAtUtc,
                $endsAtUtc,
                $timezone,
            );
        }
        if ($this->hasOverlap($tenantId, $providerId, $startsAtUtc, $endsAtUtc)) {
            return new AvailabilityDecision(
                false,
                'reservation_overlap',
                $startsAtUtc,
                $endsAtUtc,
                $timezone,
            );
        }

        return new AvailabilityDecision(
            true,
            'available',
            $startsAtUtc,
            $endsAtUtc,
            $timezone,
        );
    }

    /** @return array{Service, ServiceProvider|null, Location} */
    private function context(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
    ): array {
        $service = Service::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($serviceId)
            ->where('is_active', true)
            ->firstOrFail();
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($locationId)
            ->where('is_active', true)
            ->firstOrFail();
        if (! DB::table('service_locations')
            ->where('tenant_id', $tenantId)
            ->where('service_id', $serviceId)
            ->where('location_id', $locationId)
            ->exists()) {
            $this->notFound(Service::class, [$serviceId]);
        }

        $provider = null;
        if ($providerId !== null) {
            $provider = ServiceProvider::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($providerId)
                ->where('is_active', true)
                ->firstOrFail();
            if (! DB::table('service_provider_locations')
                ->where('tenant_id', $tenantId)
                ->where('service_provider_id', $providerId)
                ->where('location_id', $locationId)
                ->exists()) {
                $this->notFound(ServiceProvider::class, [$providerId]);
            }
        }

        return [$service, $provider, $location];
    }

    /** @return list<object> */
    private function ranges(
        string $tenantId,
        int $serviceId,
        ?int $providerId,
        string $locationId,
        string $date,
    ): array {
        $weekday = CarbonImmutable::parse($date, 'UTC')->dayOfWeekIso;
        $rules = DB::table('availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->where(function ($query) use ($serviceId): void {
                $query->whereNull('service_id')->orWhere('service_id', $serviceId);
            })
            ->when(
                $providerId === null,
                fn ($query) => $query->whereNull('service_provider_id'),
                fn ($query) => $query->where(function ($nested) use ($providerId): void {
                    $nested->whereNull('service_provider_id')
                        ->orWhere('service_provider_id', $providerId);
                }),
            )
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date);
            })
            ->get();
        $rules = $this->mostSpecific($rules, $serviceId, $providerId);

        $exceptions = DB::table('availability_exceptions')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('local_date', $date)
            ->where(function ($query) use ($serviceId): void {
                $query->whereNull('service_id')->orWhere('service_id', $serviceId);
            })
            ->when(
                $providerId === null,
                fn ($query) => $query->whereNull('service_provider_id'),
                fn ($query) => $query->where(function ($nested) use ($providerId): void {
                    $nested->whereNull('service_provider_id')
                        ->orWhere('service_provider_id', $providerId);
                }),
            )
            ->get();
        $exceptions = $this->mostSpecific($exceptions, $serviceId, $providerId);
        if ($exceptions->contains(fn (object $row): bool => $row->kind === 'closed')) {
            return [];
        }
        $open = $exceptions
            ->filter(fn (object $row): bool => $row->kind === 'open')
            ->values();
        $selected = $open->isNotEmpty() ? $open : $rules;

        return $selected
            ->sortBy([
                ['starts_at_local', 'asc'],
                ['ends_at_local', 'asc'],
            ])
            ->all();
    }

    /** @param Collection<int, object> $rows @return Collection<int, object> */
    private function mostSpecific(Collection $rows, int $serviceId, ?int $providerId): Collection
    {
        if ($providerId !== null
            && $rows->contains(fn (object $row): bool => (int) $row->service_provider_id === $providerId)) {
            $rows = $rows
                ->filter(fn (object $row): bool => (int) $row->service_provider_id === $providerId)
                ->values();
        } else {
            $rows = $rows->filter(fn (object $row): bool => $row->service_provider_id === null)->values();
        }
        if ($rows->contains(fn (object $row): bool => (int) $row->service_id === $serviceId)) {
            $rows = $rows
                ->filter(fn (object $row): bool => (int) $row->service_id === $serviceId)
                ->values();
        } else {
            $rows = $rows->filter(fn (object $row): bool => $row->service_id === null)->values();
        }
        if ($rows->contains(fn (object $row): bool => $row->source === 'canonical')) {
            return $rows->filter(fn (object $row): bool => $row->source === 'canonical')->values();
        }

        return $rows
            ->filter(fn (object $row): bool => $row->source === 'legacy_provider_schedule')
            ->values();
    }

    private function hasOverlap(
        string $tenantId,
        ?int $providerId,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        if ($providerId === null) {
            return false;
        }

        return DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('service_provider_id', $providerId)
            ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
            ->where('scheduled_at', '<', $endsAtUtc->format('Y-m-d H:i:s'))
            ->where('end_at', '>', $startsAtUtc->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function wallToUtc(
        string $date,
        string $time,
        string $timezone,
        bool $allowAmbiguous,
    ): ?CarbonImmutable {
        $status = $this->wallStatus($date, $time, $timezone);
        if ($status === 'nonexistent' || ($status === 'ambiguous' && ! $allowAmbiguous)) {
            return null;
        }
        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", $timezone);
        if (! $local instanceof CarbonImmutable || $local->format('Y-m-d H:i') !== "{$date} {$time}") {
            return null;
        }

        return $local->utc();
    }

    private function wallStatus(string $date, string $time, string $timezone): string
    {
        $wallTimestamp = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            "{$date} {$time}",
            'UTC',
        )->timestamp;
        $transitions = (new DateTimeZone($timezone))->getTransitions(
            $wallTimestamp - 172_800,
            $wallTimestamp + 172_800,
        );
        if ($transitions === false || count($transitions) < 2) {
            return 'valid';
        }
        $previousOffset = (int) $transitions[0]['offset'];
        foreach (array_slice($transitions, 1) as $transition) {
            $newOffset = (int) $transition['offset'];
            $transitionUtc = (int) $transition['ts'];
            if ($newOffset > $previousOffset
                && $wallTimestamp >= $transitionUtc + $previousOffset
                && $wallTimestamp < $transitionUtc + $newOffset) {
                return 'nonexistent';
            }
            if ($newOffset < $previousOffset
                && $wallTimestamp >= $transitionUtc + $newOffset
                && $wallTimestamp < $transitionUtc + $previousOffset) {
                return 'ambiguous';
            }
            $previousOffset = $newOffset;
        }

        return 'valid';
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    /** @param list<int|string> $ids */
    private function notFound(string $model, array $ids): never
    {
        $exception = new ModelNotFoundException;
        $exception->setModel($model, $ids);

        throw $exception;
    }
}
