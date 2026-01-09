<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * TenantSettingsDTO - Strongly-Typed Tenant Configuration Container
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Schema Enforcement for JSON Columns            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: JSON columns suffer from "Schema Drift"                        ║
 * ║    - No type checking: $tenant->settings['brand_color'] might not exist ║
 * ║    - No IDE autocomplete or static analysis                             ║
 * ║    - New keys added in deployments crash old records                    ║
 * ║                                                                          ║
 * ║  SOLUTION: DTO acts as a "Document Schema" enforcer                      ║
 * ║    - All properties are strictly typed                                  ║
 * ║    - Fail-Safe Hydration: missing keys get sensible defaults            ║
 * ║    - IDE autocomplete + PHPStan support                                 ║
 * ║                                                                          ║
 * ║  BACKWARD COMPATIBILITY:                                                 ║
 * ║    Old JSON: {"brand_color": "#3B82F6"}                                 ║
 * ║    New DTO: brand_color + invoice_prefix + modules_enabled              ║
 * ║    Result: Old record hydrates with defaults for new fields             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in Model:
 *   protected $casts = ['settings' => TenantConfigCast::class];
 *   $tenant->settings->brandColor; // IDE autocomplete works!
 */
final readonly class TenantSettingsDTO
{
    public function __construct(
        // ═══════════════════════════════════════════════════════════════════════
        // BRANDING CONFIGURATION
        // ═══════════════════════════════════════════════════════════════════════

        /** Primary brand color (hex format) */
        public string $brandColor = '#3B82F6',

        /** Secondary brand color */
        public string $secondaryColor = '#1E40AF',

        /** Custom logo URL (nullable) */
        public ?string $logoUrl = null,

        /** Favicon URL (nullable) */
        public ?string $faviconUrl = null,

        // ═══════════════════════════════════════════════════════════════════════
        // INVOICE & BILLING CONFIGURATION
        // ═══════════════════════════════════════════════════════════════════════

        /** Prefix for invoice numbers (e.g., "INV-2024-") */
        public string $invoicePrefix = 'INV-',

        /** Next invoice sequence number */
        public int $invoiceNextNumber = 1,

        /** Tax identification number */
        public ?string $taxId = null,

        /** Default tax rate percentage */
        public float $defaultTaxRate = 15.0,

        // ═══════════════════════════════════════════════════════════════════════
        // FEATURE FLAGS & MODULES
        // ═══════════════════════════════════════════════════════════════════════

        /** Enabled platform modules */
        public array $modulesEnabled = ['orders', 'products', 'customers'],

        /** Enable WhatsApp integration */
        public bool $whatsappEnabled = false,

        /** Enable inventory management */
        public bool $inventoryEnabled = true,

        /** Enable coupon system */
        public bool $couponsEnabled = true,

        /** Enable delivery tracking */
        public bool $deliveryTrackingEnabled = false,

        // ═══════════════════════════════════════════════════════════════════════
        // OPERATIONAL SETTINGS
        // ═══════════════════════════════════════════════════════════════════════

        /** Default currency code */
        public string $currency = 'HNL',

        /** Timezone for the tenant */
        public string $timezone = 'America/Tegucigalpa',

        /** Locale for formatting */
        public string $locale = 'es_HN',

        /** Minimum order amount in cents */
        public int $minimumOrderAmount = 0,

        /** Order auto-confirmation timeout in minutes (0 = disabled) */
        public int $orderAutoConfirmMinutes = 0,

        // ═══════════════════════════════════════════════════════════════════════
        // NOTIFICATION PREFERENCES
        // ═══════════════════════════════════════════════════════════════════════

        /** Email for receiving order notifications */
        public ?string $notificationEmail = null,

        /** Phone for SMS notifications */
        public ?string $notificationPhone = null,

        /** Enable email notifications */
        public bool $emailNotificationsEnabled = true,

        /** Enable SMS notifications */
        public bool $smsNotificationsEnabled = false,
    ) {}

    /**
     * Factory method for Fail-Safe Hydration from JSON/array data.
     *
     * BEHAVIOR:
     * - Missing keys → Use default values from constructor
     * - Extra keys → Silently ignored (forward compatibility)
     * - Invalid types → Coerced to expected type when safe
     *
     * @param  array<string, mixed>|null  $data  Raw JSON-decoded data
     */
    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        return new self(
            // Branding
            brandColor: self::getString($data, 'brand_color', '#3B82F6'),
            secondaryColor: self::getString($data, 'secondary_color', '#1E40AF'),
            logoUrl: self::getNullableString($data, 'logo_url'),
            faviconUrl: self::getNullableString($data, 'favicon_url'),

            // Invoice & Billing
            invoicePrefix: self::getString($data, 'invoice_prefix', 'INV-'),
            invoiceNextNumber: self::getInt($data, 'invoice_next_number', 1),
            taxId: self::getNullableString($data, 'tax_id'),
            defaultTaxRate: self::getFloat($data, 'default_tax_rate', 15.0),

            // Feature Flags
            modulesEnabled: self::getArray($data, 'modules_enabled', ['orders', 'products', 'customers']),
            whatsappEnabled: self::getBool($data, 'whatsapp_enabled', false),
            inventoryEnabled: self::getBool($data, 'inventory_enabled', true),
            couponsEnabled: self::getBool($data, 'coupons_enabled', true),
            deliveryTrackingEnabled: self::getBool($data, 'delivery_tracking_enabled', false),

            // Operational
            currency: self::getString($data, 'currency', 'HNL'),
            timezone: self::getString($data, 'timezone', 'America/Tegucigalpa'),
            locale: self::getString($data, 'locale', 'es_HN'),
            minimumOrderAmount: self::getInt($data, 'minimum_order_amount', 0),
            orderAutoConfirmMinutes: self::getInt($data, 'order_auto_confirm_minutes', 0),

            // Notifications
            notificationEmail: self::getNullableString($data, 'notification_email'),
            notificationPhone: self::getNullableString($data, 'notification_phone'),
            emailNotificationsEnabled: self::getBool($data, 'email_notifications_enabled', true),
            smsNotificationsEnabled: self::getBool($data, 'sms_notifications_enabled', false),
        );
    }

    /**
     * Convert DTO to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            // Branding
            'brand_color' => $this->brandColor,
            'secondary_color' => $this->secondaryColor,
            'logo_url' => $this->logoUrl,
            'favicon_url' => $this->faviconUrl,

            // Invoice & Billing
            'invoice_prefix' => $this->invoicePrefix,
            'invoice_next_number' => $this->invoiceNextNumber,
            'tax_id' => $this->taxId,
            'default_tax_rate' => $this->defaultTaxRate,

            // Feature Flags
            'modules_enabled' => $this->modulesEnabled,
            'whatsapp_enabled' => $this->whatsappEnabled,
            'inventory_enabled' => $this->inventoryEnabled,
            'coupons_enabled' => $this->couponsEnabled,
            'delivery_tracking_enabled' => $this->deliveryTrackingEnabled,

            // Operational
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'minimum_order_amount' => $this->minimumOrderAmount,
            'order_auto_confirm_minutes' => $this->orderAutoConfirmMinutes,

            // Notifications
            'notification_email' => $this->notificationEmail,
            'notification_phone' => $this->notificationPhone,
            'email_notifications_enabled' => $this->emailNotificationsEnabled,
            'sms_notifications_enabled' => $this->smsNotificationsEnabled,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER METHODS (Named Constructors Pattern)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a copy with modified values.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * Check if a module is enabled.
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modulesEnabled, true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TYPE-SAFE EXTRACTORS (Defensive Programming)
    // ═══════════════════════════════════════════════════════════════════════

    private static function getString(array $data, string $key, string $default): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;
    }

    private static function getNullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }

    private static function getInt(array $data, string $key, int $default): int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : $default;
    }

    private static function getFloat(array $data, string $key, float $default): float
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : $default;
    }

    private static function getBool(array $data, string $key, bool $default): bool
    {
        return isset($data[$key]) ? (bool) $data[$key] : $default;
    }

    private static function getArray(array $data, string $key, array $default): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $default;
    }
}
