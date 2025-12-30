<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tenancy;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/**
 * TenantSettings - Strongly-Typed Tenant Configuration Container
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HYDRATION STRATEGY: Safe Extraction from Multiple Sources              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  fromArray():   Database JSON → DTO (Eloquent Cast)                     ║
 * ║  fromRequest(): HTTP Request → DTO (Controller input)                   ║
 * ║  toArray():     DTO → JSON (API response / Inertia)                     ║
 * ║                                                                          ║
 * ║  FAIL-SAFE HYDRATION:                                                   ║
 * ║    Missing keys get sensible defaults (backward compatibility)          ║
 * ║    Extra keys silently ignored (forward compatibility)                  ║
 * ║    Invalid types coerced when safe (resilience)                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TenantSettings implements Arrayable, \JsonSerializable
{
    public function __construct(
        // ═══════════════════════════════════════════════════════════════════════
        // BRANDING CONFIGURATION
        // ═══════════════════════════════════════════════════════════════════════
        
        public string $brandColor = '#3B82F6',
        public string $secondaryColor = '#1E40AF',
        public ?string $logoUrl = null,
        public ?string $faviconUrl = null,
        
        // ═══════════════════════════════════════════════════════════════════════
        // INVOICE & BILLING CONFIGURATION
        // ═══════════════════════════════════════════════════════════════════════
        
        public string $invoicePrefix = 'INV-',
        public int $invoiceNextNumber = 1,
        public ?string $taxId = null,
        public float $defaultTaxRate = 15.0,
        
        // ═══════════════════════════════════════════════════════════════════════
        // FEATURE FLAGS & MODULES
        // ═══════════════════════════════════════════════════════════════════════
        
        /** @var array<string> */
        public array $modulesEnabled = ['orders', 'products', 'customers'],
        public bool $whatsappEnabled = false,
        public bool $inventoryEnabled = true,
        public bool $couponsEnabled = true,
        public bool $deliveryTrackingEnabled = false,
        
        // ═══════════════════════════════════════════════════════════════════════
        // OPERATIONAL SETTINGS
        // ═══════════════════════════════════════════════════════════════════════
        
        public string $currency = 'HNL',
        public string $timezone = 'America/Tegucigalpa',
        public string $locale = 'es_HN',
        public int $minimumOrderAmount = 0,
        public int $orderAutoConfirmMinutes = 0,
        
        // ═══════════════════════════════════════════════════════════════════════
        // NOTIFICATION PREFERENCES
        // ═══════════════════════════════════════════════════════════════════════
        
        public ?string $notificationEmail = null,
        public ?string $notificationPhone = null,
        public bool $emailNotificationsEnabled = true,
        public bool $smsNotificationsEnabled = false,
    ) {}

    /**
     * Hydrate from database JSON (Eloquent Cast).
     */
    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        return new self(
            brandColor: self::string($data, 'brand_color', '#3B82F6'),
            secondaryColor: self::string($data, 'secondary_color', '#1E40AF'),
            logoUrl: self::nullableString($data, 'logo_url'),
            faviconUrl: self::nullableString($data, 'favicon_url'),
            invoicePrefix: self::string($data, 'invoice_prefix', 'INV-'),
            invoiceNextNumber: self::int($data, 'invoice_next_number', 1),
            taxId: self::nullableString($data, 'tax_id'),
            defaultTaxRate: self::float($data, 'default_tax_rate', 15.0),
            modulesEnabled: self::array($data, 'modules_enabled', ['orders', 'products', 'customers']),
            whatsappEnabled: self::bool($data, 'whatsapp_enabled', false),
            inventoryEnabled: self::bool($data, 'inventory_enabled', true),
            couponsEnabled: self::bool($data, 'coupons_enabled', true),
            deliveryTrackingEnabled: self::bool($data, 'delivery_tracking_enabled', false),
            currency: self::string($data, 'currency', 'HNL'),
            timezone: self::string($data, 'timezone', 'America/Tegucigalpa'),
            locale: self::string($data, 'locale', 'es_HN'),
            minimumOrderAmount: self::int($data, 'minimum_order_amount', 0),
            orderAutoConfirmMinutes: self::int($data, 'order_auto_confirm_minutes', 0),
            notificationEmail: self::nullableString($data, 'notification_email'),
            notificationPhone: self::nullableString($data, 'notification_phone'),
            emailNotificationsEnabled: self::bool($data, 'email_notifications_enabled', true),
            smsNotificationsEnabled: self::bool($data, 'sms_notifications_enabled', false),
        );
    }

    /**
     * Hydrate from HTTP Request (Controller input).
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Serialize to array (API response / Inertia).
     */
    public function toArray(): array
    {
        return [
            'brand_color' => $this->brandColor,
            'secondary_color' => $this->secondaryColor,
            'logo_url' => $this->logoUrl,
            'favicon_url' => $this->faviconUrl,
            'invoice_prefix' => $this->invoicePrefix,
            'invoice_next_number' => $this->invoiceNextNumber,
            'tax_id' => $this->taxId,
            'default_tax_rate' => $this->defaultTaxRate,
            'modules_enabled' => $this->modulesEnabled,
            'whatsapp_enabled' => $this->whatsappEnabled,
            'inventory_enabled' => $this->inventoryEnabled,
            'coupons_enabled' => $this->couponsEnabled,
            'delivery_tracking_enabled' => $this->deliveryTrackingEnabled,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'minimum_order_amount' => $this->minimumOrderAmount,
            'order_auto_confirm_minutes' => $this->orderAutoConfirmMinutes,
            'notification_email' => $this->notificationEmail,
            'notification_phone' => $this->notificationPhone,
            'email_notifications_enabled' => $this->emailNotificationsEnabled,
            'sms_notifications_enabled' => $this->smsNotificationsEnabled,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modulesEnabled, true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TYPE-SAFE EXTRACTORS
    // ═══════════════════════════════════════════════════════════════════════

    private static function string(array $data, string $key, string $default): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;
    }

    private static function nullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }

    private static function int(array $data, string $key, int $default): int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : $default;
    }

    private static function float(array $data, string $key, float $default): float
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : $default;
    }

    private static function bool(array $data, string $key, bool $default): bool
    {
        return isset($data[$key]) ? (bool) $data[$key] : $default;
    }

    private static function array(array $data, string $key, array $default): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $default;
    }
}
