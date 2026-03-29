<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Application\Tenant\Contracts\TenantProfileWriterInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Models\OpeningHour;
use App\Models\Tenant;

final class EloquentTenantProfileWriter implements TenantProfileWriterInterface
{
    /**
     * @var list<string>
     */
    private const PANEL_SETTINGS_COLUMNS = [
        'name',
        'whatsapp_number',
        'description',
        'address',
        'whatsapp_message',
    ];

    /**
     * @var list<string>
     */
    private const MARKETING_COLUMNS = [
        'whatsapp_number',
        'whatsapp_message',
    ];

    /**
     * @var list<string>
     */
    private const PUBLIC_PROFILE_COLUMNS = [
        'name',
        'slug',
        'slogan',
        'description',
        'about',
        'category_id',
        'logo_path',
        'brand_color',
        'hero_image',
        'cover_path',
        'primary_color',
        'email',
        'phone',
        'whatsapp_number',
        'city_id',
        'address',
        'latitude',
        'longitude',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'website_url',
        'simple_schedule',
        'whatsapp_message',
    ];

    /**
     * @var array<string, int>
     */
    private const DAY_MAP = [
        'domingo' => 0,
        'sunday' => 0,
        'lunes' => 1,
        'monday' => 1,
        'martes' => 2,
        'tuesday' => 2,
        'miercoles' => 3,
        'miércoles' => 3,
        'wednesday' => 3,
        'jueves' => 4,
        'thursday' => 4,
        'viernes' => 5,
        'friday' => 5,
        'sabado' => 6,
        'sábado' => 6,
        'saturday' => 6,
    ];

    public function updatePanelSettings(string $tenantId, array $attributes): void
    {
        $this->updateTenantColumns($tenantId, $attributes, self::PANEL_SETTINGS_COLUMNS);
    }

    public function updateMarketingSettings(string $tenantId, array $attributes): void
    {
        $this->updateTenantColumns($tenantId, $attributes, self::MARKETING_COLUMNS);
    }

    public function updatePublicProfile(string $tenantId, array $attributes): void
    {
        $this->updateTenantColumns($tenantId, $attributes, self::PUBLIC_PROFILE_COLUMNS);
    }

    public function syncOpeningHours(string $tenantId, array $hoursData): void
    {
        $tenant = $this->findTenantOrFail($tenantId);
        $normalizedRows = $this->normalizeOpeningHours($tenantId, $hoursData);

        OpeningHour::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->delete();

        foreach ($normalizedRows as $row) {
            $openingHour = new OpeningHour;
            $openingHour->forceFill($row);
            $openingHour->save();
        }
    }

    public function clearOpeningHours(string $tenantId): void
    {
        $tenant = $this->findTenantOrFail($tenantId);

        OpeningHour::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowedColumns
     */
    private function updateTenantColumns(string $tenantId, array $attributes, array $allowedColumns): void
    {
        $tenant = $this->findTenantOrFail($tenantId);
        $payload = [];

        foreach ($allowedColumns as $column) {
            if (! array_key_exists($column, $attributes)) {
                continue;
            }

            $payload[$column] = $attributes[$column];
        }

        if ($payload === []) {
            return;
        }

        $tenant->forceFill($payload);
        $tenant->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $hoursData
     * @return list<array<string, int|string>>
     */
    private function normalizeOpeningHours(string $tenantId, array $hoursData): array
    {
        $rowsByDay = [];

        foreach ($hoursData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $day = $this->resolveDayOfWeek($row['day'] ?? null);
            $openingTime = $this->normalizeTime($row['open'] ?? null);
            $closingTime = $this->normalizeTime($row['close'] ?? null);
            $isClosed = filter_var($row['is_closed'] ?? false, FILTER_VALIDATE_BOOL);

            if ($day === null || $isClosed || $openingTime === null || $closingTime === null) {
                continue;
            }

            $rowsByDay[$day] = [
                'tenant_id' => $tenantId,
                'day_of_week' => $day,
                'opening_time' => $openingTime,
                'closing_time' => $closingTime,
            ];
        }

        ksort($rowsByDay);

        return array_values($rowsByDay);
    }

    private function resolveDayOfWeek(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $day = mb_strtolower(trim((string) $value));

        return self::DAY_MAP[$day] ?? null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        return null;
    }

    /**
     * @throws TenantNotFoundException
     */
    private function findTenantOrFail(string $tenantId): Tenant
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->find($tenantId);

        if (! $tenant instanceof Tenant) {
            throw TenantNotFoundException::withId($tenantId);
        }

        return $tenant;
    }
}
