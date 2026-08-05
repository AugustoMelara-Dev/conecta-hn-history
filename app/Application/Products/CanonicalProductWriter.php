<?php

declare(strict_types=1);

namespace App\Application\Products;

use App\Application\Markets\TenantMarketResolver;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\InventoryManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative write owner for the operational Product aggregate.
 *
 * Adapters may only supply tenant-scoped commands and the allow-listed product
 * attributes below. Ratings and blur_hash remain derived enrichments.
 */
final class CanonicalProductWriter
{
    /** @var array<string, true> */
    private const MUTABLE_ATTRIBUTES = [
        'name' => true, 'category_id' => true, 'city_id' => true, 'menu_section_id' => true,
        'description' => true, 'short_description' => true, 'tags' => true,
        'colors' => true, 'material' => true, 'dimensions' => true,
        'variants' => true, 'condition' => true, 'warnings' => true,
        'unit' => true, 'image_path' => true, 'is_featured' => true,
        'is_new' => true, 'is_impulse_buy' => true, 'allow_backorders' => true,
        'low_stock_threshold' => true, 'manage_stock' => true,
    ];

    public function __construct(
        private readonly PlanFeatureFlagService $planFeatures,
        private readonly InventoryManager $inventory,
        private readonly TenantMarketResolver $marketResolver,
    ) {}

    public function create(CreateCanonicalProductCommand $command): Product
    {
        $this->assertCreateCommand($command);

        return DB::transaction(function () use ($command): Product {
            $tenant = Tenant::query()->whereKey($command->tenantId)->lockForUpdate()->firstOrFail();
            $market = $this->marketResolver->resolve($tenant);
            $minorUnit = $this->currencyMinorUnit($market->currencyCode);
            $this->assertPriceFitsLegacyProjection($command->priceMinor, $minorUnit);
            $payloadHash = $this->hashCreateCommand($command, $market->currencyCode);
            // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            $existing = Product::withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('write_idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Product) {
                if (! hash_equals((string) $existing->write_payload_hash, $payloadHash)) {
                    throw new \LogicException('Product idempotency key was reused with an incompatible payload.');
                }

                return $existing;
            }

            // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            $count = Product::withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->whereNull('deleted_at')
                ->count();
            if ($count >= $this->planFeatures->getMaxProductLimit($tenant)) {
                throw ValidationException::withMessages([
                    'product_limit' => ['Tu negocio alcanzó el límite de productos de su plan.'],
                ]);
            }

            $attributes = $this->safeAttributes($command->attributes);
            $this->assertReferences($command->tenantId, $attributes);
            $manageStock = $this->effectiveManageStock($attributes);
            unset($attributes['manage_stock']);
            $product = new Product;
            $product->forceFill(array_merge($attributes, [
                'tenant_id' => $command->tenantId,
                'name' => $command->name,
                'slug' => (Str::slug($command->name) ?: 'producto').'-'.substr(hash('sha256', $command->idempotencyKey), 0, 12),
                'price' => $this->minorUnitsToDecimal($command->priceMinor, $minorUnit),
                'price_minor' => $command->priceMinor,
                'currency' => $market->currencyCode,
                'manage_stock' => $manageStock,
                'stock_quantity' => 0,
                'stock_status' => $manageStock ? StockStatus::OutOfStock : StockStatus::InStock,
                'is_active' => true,
                'is_visible' => $command->published,
                'write_idempotency_key' => $command->idempotencyKey,
                'write_payload_hash' => $payloadHash,
            ]))->save();

            if ($command->initialStockQuantity !== 0) {
                $this->inventory->adjust(
                    tenantId: $command->tenantId,
                    productId: (int) $product->getKey(),
                    delta: $command->initialStockQuantity,
                    reason: 'initial_stock',
                    idempotencyKey: 'product-create:'.$command->idempotencyKey,
                    referenceType: 'product_create',
                    referenceId: $command->idempotencyKey,
                );
            }

            return $product->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $tenantId, int $productId, array $attributes): Product
    {
        $attributes = $this->safeAttributes($attributes, true);
        if (array_key_exists('price_minor', $attributes)) {
            if (! is_int($attributes['price_minor'])) {
                throw new \InvalidArgumentException('price_minor must be an integer.');
            }
        }

        return DB::transaction(function () use ($tenantId, $productId, $attributes): Product {
            $product = $this->tenantProduct($tenantId, $productId, true);
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $market = $this->marketResolver->resolve($tenant);
            $this->assertReferences($tenantId, $attributes);
            if (array_key_exists('price_minor', $attributes)) {
                $priceMinor = $attributes['price_minor'];
                $currency = (string) ($product->currency ?: $market->currencyCode);
                $minorUnit = $this->currencyMinorUnit($currency);
                $this->assertPriceFitsLegacyProjection($priceMinor, $minorUnit);
                $attributes['price'] = $this->minorUnitsToDecimal($priceMinor, $minorUnit);
                $attributes['currency'] = $currency;
            }
            $product->forceFill($attributes)->save();

            return $product->fresh();
        });
    }

    public function publish(string $tenantId, int $productId, bool $published = true): Product
    {
        return DB::transaction(function () use ($tenantId, $productId, $published): Product {
            $product = $this->tenantProduct($tenantId, $productId, true);
            $product->forceFill(['is_visible' => $published])->save();

            return $product->fresh();
        });
    }

    public function delete(string $tenantId, int $productId): void
    {
        DB::transaction(function () use ($tenantId, $productId): void {
            // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            $product = Product::withoutGlobalScopes()->withTrashed()
                ->whereKey($productId)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if ($product->deleted_at === null) {
                $product->delete();
            }
        });
    }

    public function adjustStock(string $tenantId, int $productId, int $delta, string $reason, string $idempotencyKey): Product
    {
        return $this->inventory->adjust($tenantId, $productId, $delta, $reason, $idempotencyKey, 'catalog_adjustment', $idempotencyKey);
    }

    public function setStock(string $tenantId, int $productId, int $quantity, string $idempotencyKey): Product
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('stock_quantity must be non-negative.');
        }

        return DB::transaction(function () use ($tenantId, $productId, $quantity, $idempotencyKey): Product {
            $product = $this->tenantProduct($tenantId, $productId, true);
            $delta = $quantity - (int) $product->stock_quantity;
            if ($delta === 0) {
                return $product;
            }

            return $this->inventory->adjust($tenantId, $productId, $delta, 'catalog_stock_set:'.$quantity, $idempotencyKey, 'catalog_update', $idempotencyKey);
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function safeAttributes(array $attributes, bool $allowPriceMinor = false): array
    {
        if (! $allowPriceMinor && array_key_exists('price_minor', $attributes)) {
            throw new \InvalidArgumentException('price_minor belongs to the typed command, not attributes.');
        }
        $unknown = array_diff(array_keys($attributes), array_keys(self::MUTABLE_ATTRIBUTES), $allowPriceMinor ? ['price_minor'] : []);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown or system-owned product attributes: '.implode(', ', $unknown));
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function assertReferences(string $tenantId, array $attributes): void
    {
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        if (isset($attributes['menu_section_id']) && ! MenuSection::withoutGlobalScopes()
            ->whereKey($attributes['menu_section_id'])->where('tenant_id', $tenantId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['menu_section_id' => ['La sección no pertenece al negocio.']]);
        }
        // Categories are shared taxonomy, not tenant-owned records.
        if (isset($attributes['category_id']) && ! Category::query()
            ->whereKey($attributes['category_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category_id' => ['La categoría global no está activa.']]);
        }
    }

    private function tenantProduct(string $tenantId, int $productId, bool $lock): Product
    {
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        $query = Product::withoutGlobalScopes()
            ->whereKey($productId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function assertCreateCommand(CreateCanonicalProductCommand $command): void
    {
        if (! Str::isUuid($command->tenantId) || $command->idempotencyKey === '' || trim($command->name) === '') {
            throw new \InvalidArgumentException('Product creation requires a UUID tenant, name, and idempotency key.');
        }
        if ($command->priceMinor < 0 || $command->initialStockQuantity < 0) {
            throw new \InvalidArgumentException('Price and initial stock must be non-negative.');
        }
        if (array_key_exists('manage_stock', $command->attributes) && ! is_bool($command->attributes['manage_stock'])) {
            throw new \InvalidArgumentException('manage_stock must be a boolean.');
        }
        if (! $this->effectiveManageStock($command->attributes) && $command->initialStockQuantity !== 0) {
            throw new \InvalidArgumentException('Unmanaged products cannot declare an initial stock quantity.');
        }
        $this->safeAttributes($command->attributes);
    }

    private function minorUnitsToDecimal(int $minor, int $minorUnit): string
    {
        return number_format($minor / (10 ** $minorUnit), 2, '.', '');
    }

    private function hashCreateCommand(CreateCanonicalProductCommand $command, string $currency): string
    {
        return hash('sha256', json_encode([
            'name' => $command->name,
            'price_minor' => $command->priceMinor,
            'currency' => $currency,
            'initial_stock_quantity' => $command->initialStockQuantity,
            'attributes' => $this->canonicalize(array_merge($command->attributes, [
                'manage_stock' => $this->effectiveManageStock($command->attributes),
            ])),
            'published' => $command->published,
        ], JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $attributes */
    private function effectiveManageStock(array $attributes): bool
    {
        return (bool) ($attributes['manage_stock'] ?? true);
    }

    private function currencyMinorUnit(string $currency): int
    {
        $minorUnit = DB::table('currencies')->where('code', $currency)->value('minor_unit');
        if (! is_numeric($minorUnit) || (int) $minorUnit < 0 || (int) $minorUnit > 6) {
            throw ValidationException::withMessages([
                'currency' => ['Product currency minor-unit metadata is unavailable.'],
            ]);
        }

        return (int) $minorUnit;
    }

    private function assertPriceFitsLegacyProjection(int $priceMinor, int $minorUnit): void
    {
        $scale = 10 ** $minorUnit;
        $maxMinor = 99_999_999 * $scale + ($scale - 1);
        if ($priceMinor < 0 || $priceMinor > $maxMinor) {
            throw new \InvalidArgumentException('price_minor exceeds the legacy decimal compatibility range.');
        }
    }
}
