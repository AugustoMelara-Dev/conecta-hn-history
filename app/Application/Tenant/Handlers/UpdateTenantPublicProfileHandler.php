<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Tenant\Commands\UpdateTenantPublicProfileCommand;
use App\Application\Tenant\Contracts\TenantProfileWriterInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

final readonly class UpdateTenantPublicProfileHandler
{
    /**
     * @var list<string>
     */
    private const PRO_ONLY_FIELDS = [
        'brand_color',
        'hero_image',
        'cover_path',
        'primary_color',
        'latitude',
        'longitude',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'website_url',
    ];

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantProfileWriterInterface $tenantProfileWriter,
    ) {}

    /**
     * @throws TenantNotFoundException
     */
    public function handle(UpdateTenantPublicProfileCommand $command): void
    {
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        $profileData = $this->normalizeProfileData($command->profileData);
        $hoursData = $command->hoursData;

        if ($tenant->isPro()) {
            $profileData['simple_schedule'] = null;
        } else {
            foreach (self::PRO_ONLY_FIELDS as $field) {
                unset($profileData[$field]);
            }

            $hoursData = null;
        }

        $this->tenantProfileWriter->updatePublicProfile($command->tenantId, $profileData);

        if ($tenant->isPro()) {
            if ($hoursData !== null) {
                $this->tenantProfileWriter->syncOpeningHours($command->tenantId, $hoursData);
            }

            return;
        }

        $this->tenantProfileWriter->clearOpeningHours($command->tenantId);
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @return array<string, mixed>
     */
    private function normalizeProfileData(array $profileData): array
    {
        unset($profileData['hours_data']);

        foreach ([
            'name',
            'slug',
            'slogan',
            'description',
            'about',
            'logo_path',
            'brand_color',
            'hero_image',
            'cover_path',
            'primary_color',
            'email',
            'phone',
            'whatsapp_number',
            'address',
            'latitude',
            'longitude',
            'facebook_url',
            'instagram_url',
            'tiktok_url',
            'website_url',
            'simple_schedule',
            'whatsapp_message',
        ] as $field) {
            if (! array_key_exists($field, $profileData)) {
                continue;
            }

            $profileData[$field] = $this->normalizeNullableString($profileData[$field]);
        }

        return $profileData;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
