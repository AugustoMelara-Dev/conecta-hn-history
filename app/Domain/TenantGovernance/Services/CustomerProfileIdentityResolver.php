<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class CustomerProfileIdentityResolver
{
    public function __construct(
        private readonly CustomerProfileRepositoryInterface $customerProfileRepository,
        private readonly CustomerProfileWorkspaceRelinker $workspaceRelinker,
    ) {}

    /**
     * @return array{0: CustomerProfile|null, 1: int|null}
     */
    public function syncSourceRecord(
        Model $source,
        string $tenantId,
        ?string $name,
        ?string $email,
        ?string $phone,
        ?int $previousProfileId = null,
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = $this->normalizePhone($phone);
        $currentProfileId = $this->normalizeProfileId($source->getAttribute('customer_profile_id'));
        $previousProfileId ??= $currentProfileId;
        $currentProfile = $this->findProfileById($tenantId, $currentProfileId);

        if ($normalizedEmail === null && $normalizedPhone === null) {
            $this->updateSourceLink($source, null);

            return [null, $previousProfileId];
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

        return [$profile->fresh(), $previousProfileId];
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

        $duplicates->each(fn (CustomerProfile $duplicate) => $this->workspaceRelinker->relink($canonical, $duplicate));

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

    private function normalizeProfileId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
