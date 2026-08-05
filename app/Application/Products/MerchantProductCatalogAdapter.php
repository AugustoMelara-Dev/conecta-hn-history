<?php

declare(strict_types=1);

namespace App\Application\Products;

use App\Application\Markets\TenantMarketResolver;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Filament-facing catalog adapter.
 *
 * Tenant identity is intentionally a Tenant model from Filament's trusted
 * context. Form data is never allowed to select a tenant or bypass the
 * canonical product writer.
 */
final class MerchantProductCatalogAdapter
{
    /** @var array<string, true> */
    private const FORM_ATTRIBUTES = [
        'category_id' => true, 'menu_section_id' => true, 'description' => true,
        'short_description' => true, 'tags' => true, 'colors' => true,
        'material' => true, 'dimensions' => true, 'variants' => true,
        'condition' => true, 'warnings' => true, 'unit' => true,
        'is_featured' => true, 'is_new' => true, 'is_impulse_buy' => true,
        'allow_backorders' => true, 'low_stock_threshold' => true,
        'manage_stock' => true,
    ];

    public function __construct(
        private readonly CanonicalProductWriter $writer,
        private readonly PlanFeatureFlagService $planFeatures,
        private readonly ProductImageStorageInterface $imageStorage,
        private readonly ProductMediaCleanupQueue $cleanup,
        private readonly TenantMarketResolver $marketResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Tenant $tenant, array $data, string $idempotencyKey): Product
    {
        $this->assertSafeMediaBoundary($data);

        return $this->writeWithOptionalImage(
            $tenant,
            $data['pending_image_path'] ?? null,
            false,
            $idempotencyKey,
            $this->mediaPayloadHash($data),
            null,
            function () use ($tenant, $data, $idempotencyKey): Product {
                $priceMinor = $this->marketMinor($tenant, $data['price'] ?? null);
                $initialStock = $this->quantity($data['stock_quantity'] ?? 0, 'stock_quantity');
                $attributes = $this->attributes($data);
                $attributes['city_id'] = $tenant->city_id;

                return $this->writer->create(new CreateCanonicalProductCommand(
                    tenantId: (string) $tenant->getKey(),
                    idempotencyKey: $idempotencyKey,
                    name: $this->name($data['name'] ?? null),
                    priceMinor: $priceMinor,
                    initialStockQuantity: $initialStock,
                    attributes: $attributes,
                    published: (bool) ($data['is_visible'] ?? false),
                ));
            },
        );
    }

    /** @param array<string, mixed> $data */
    public function update(Tenant $tenant, Product $product, array $data, ?string $stockIdempotencyKey = null): Product
    {
        $this->assertSafeMediaBoundary($data);

        return $this->writeWithOptionalImage(
            $tenant,
            $data['pending_image_path'] ?? null,
            (bool) ($data['remove_image'] ?? false),
            $stockIdempotencyKey,
            $this->mediaPayloadHash($data),
            (int) $product->getKey(),
            function () use ($tenant, $product, $data, $stockIdempotencyKey): Product {
                return DB::transaction(function () use ($tenant, $product, $data, $stockIdempotencyKey): Product {
                    $attributes = $this->attributes($data);
                    if (array_key_exists('name', $data)) {
                        $attributes['name'] = $this->name($data['name']);
                    }
                    if (array_key_exists('price', $data)) {
                        $attributes['price_minor'] = $this->marketMinor($tenant, $data['price']);
                    }

                    $updated = $attributes === []
                        ? $this->trustedProduct($tenant, $product)
                        : $this->writer->update((string) $tenant->getKey(), (int) $product->getKey(), $attributes);

                    if (array_key_exists('is_visible', $data)) {
                        $updated = $this->writer->publish((string) $tenant->getKey(), (int) $product->getKey(), (bool) $data['is_visible']);
                    }
                    if (array_key_exists('stock_quantity', $data)) {
                        if ($stockIdempotencyKey === null || trim($stockIdempotencyKey) === '') {
                            throw new InvalidArgumentException('Stock edits require an idempotency key.');
                        }
                        $updated = $this->writer->setStock(
                            (string) $tenant->getKey(),
                            (int) $product->getKey(),
                            $this->quantity($data['stock_quantity'], 'stock_quantity'),
                            'filament-product-stock:'.$stockIdempotencyKey,
                        );
                    }

                    return $updated;
                });
            },
        );
    }

    public function setPrice(Tenant $tenant, Product $product, mixed $price): Product
    {
        return $this->update($tenant, $product, ['price' => $price]);
    }

    public function setVisibility(Tenant $tenant, Product $product, bool $visible): Product
    {
        return $this->update($tenant, $product, ['is_visible' => $visible]);
    }

    public function setFeatured(Tenant $tenant, Product $product, bool $featured): Product
    {
        return $this->update($tenant, $product, ['is_featured' => $featured]);
    }

    public function clone(Tenant $tenant, Product $product, string $idempotencyKey): Product
    {
        $source = $this->trustedProduct($tenant, $product);
        $minorUnit = $this->marketMinorUnit($tenant);

        return $this->create($tenant, array_merge($this->attributes($source->toArray()), [
            'name' => $source->name.' (Copia)',
            'price' => $this->majorAmount((int) $source->price_minor, $minorUnit),
            'stock_quantity' => 0,
            'is_visible' => false,
            'is_featured' => false,
        ]), $idempotencyKey);
    }

    /** @param array<string, mixed> $data */
    public function createSection(Tenant $tenant, array $data): MenuSection
    {
        $name = $this->name($data['name'] ?? null);

        return DB::transaction(function () use ($tenant, $name): MenuSection {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $currentCount = DB::table('menu_sections')
                ->where('tenant_id', $tenant->getKey())
                ->count();
            if ($currentCount >= $this->planFeatures->getMaxMenuSections($lockedTenant)) {
                throw ValidationException::withMessages([
                    'menu_section_id' => ['Tu negocio alcanzó el límite de secciones de su plan.'],
                ]);
            }
            $sortOrder = (int) DB::table('menu_sections')
                ->where('tenant_id', $tenant->getKey())
                ->max('sort_order') + 1;
            $section = new MenuSection;
            $section->forceFill([
                'tenant_id' => (string) $tenant->getKey(),
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ])->save();

            return $section;
        });
    }

    /** @param iterable<Product> $products */
    public function assignSection(Tenant $tenant, iterable $products, int $sectionId): void
    {
        DB::transaction(function () use ($tenant, $products, $sectionId): void {
            foreach ($products as $product) {
                $this->writer->update((string) $tenant->getKey(), (int) $product->getKey(), [
                    'menu_section_id' => $sectionId,
                ]);
            }
        });
    }

    public function delete(Tenant $tenant, Product $product): void
    {
        $this->writer->delete((string) $tenant->getKey(), (int) $product->getKey());
    }

    /** @param iterable<Product> $products */
    public function deleteMany(Tenant $tenant, iterable $products): void
    {
        DB::transaction(function () use ($tenant, $products): void {
            foreach ($products as $product) {
                $this->delete($tenant, $product);
            }
        });
    }

    private function trustedProduct(Tenant $tenant, Product $product): Product
    {
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        return Product::withoutGlobalScopes()
            ->whereKey($product->getKey())
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        $attributes = [];
        foreach (self::FORM_ATTRIBUTES as $field => $_) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        return $attributes;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['El nombre del producto es obligatorio.']]);
        }

        return $name;
    }

    private function marketMinor(Tenant $tenant, mixed $value): int
    {
        $minorUnit = $this->marketMinorUnit($tenant);
        // Floats are intentionally rejected: binary floating point is not a
        // trustworthy source for exact currency minor units.
        $decimal = is_int($value) || is_string($value) ? trim((string) $value) : '';
        $fractionPattern = $minorUnit === 0 ? '' : '(?:\.\d{1,'.$minorUnit.'})?';
        if (! preg_match('/^\d{1,8}'.$fractionPattern.'$/', $decimal)) {
            throw ValidationException::withMessages([
                'price' => ["El precio debe usar como máximo {$minorUnit} decimales para la moneda del mercado."],
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $scale = 10 ** $minorUnit;

        return ((int) $whole * $scale)
            + ($minorUnit === 0 ? 0 : (int) str_pad($fraction, $minorUnit, '0'));
    }

    private function marketMinorUnit(Tenant $tenant): int
    {
        $currency = strtoupper($this->marketResolver->resolve($tenant)->currencyCode);
        $minorUnit = DB::table('currencies')->where('code', $currency)->value('minor_unit');
        if (! is_numeric($minorUnit) || (int) $minorUnit < 0 || (int) $minorUnit > 6) {
            throw ValidationException::withMessages([
                'price' => ['No está disponible la metadata de unidades menores de la moneda del mercado.'],
            ]);
        }

        return (int) $minorUnit;
    }

    private function majorAmount(int $minorAmount, int $minorUnit): string
    {
        if ($minorUnit === 0) {
            return (string) $minorAmount;
        }

        $scale = 10 ** $minorUnit;

        return intdiv($minorAmount, $scale).'.'.str_pad(
            (string) ($minorAmount % $scale),
            $minorUnit,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function quantity(mixed $value, string $field): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw ValidationException::withMessages([$field => ['La cantidad debe ser un entero no negativo.']]);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $data */
    private function assertSafeMediaBoundary(array $data): void
    {
        if (array_key_exists('image_path', $data) && $data['image_path'] !== null && $data['image_path'] !== '') {
            throw ValidationException::withMessages([
                'image_path' => ['La carga manual de imágenes permanece bloqueada hasta completar su normalización segura.'],
            ]);
        }
    }

    /** @param callable(): Product $write */
    private function writeWithOptionalImage(
        Tenant $tenant,
        mixed $imageState,
        bool $removeRequested,
        ?string $operationKey,
        string $payloadHash,
        ?int $knownProductId,
        callable $write,
    ): Product {
        $tenantId = (string) $tenant->getKey();
        $stagedPath = $this->imageStorage->stagedPath($tenantId, $imageState);

        if ($stagedPath !== null && $removeRequested) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['Elige reemplazar o eliminar la imagen, no ambas acciones.'],
            ]);
        }

        if ($stagedPath === null && ! $removeRequested) {
            return $write();
        }

        if ($operationKey === null || trim($operationKey) === '') {
            throw new InvalidArgumentException('Media mutations require an idempotency key.');
        }

        $operationHash = hash('sha256', $operationKey);
        $stagedPathHash = $stagedPath === null ? null : hash('sha256', $stagedPath);
        $action = $removeRequested ? 'remove' : 'replace';
        $publishedPath = null;
        $productId = null;

        try {
            $product = DB::transaction(function () use (
                $tenantId,
                $stagedPath,
                $stagedPathHash,
                $operationKey,
                $operationHash,
                $payloadHash,
                $action,
                $knownProductId,
                $write,
                &$publishedPath,
                &$productId,
            ): Product {
                if ($knownProductId !== null) {
                    $productId = $knownProductId;
                    // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
                    $lockedBeforeWrite = Product::withoutGlobalScopes()
                        ->whereKey($productId)
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->firstOrFail();
                    $existingBeforeWrite = DB::table('product_media_operations')
                        ->where('tenant_id', $tenantId)
                        ->where('operation_key_hash', $operationHash)
                        ->lockForUpdate()
                        ->first();

                    if ($existingBeforeWrite !== null) {
                        $this->assertCompatibleMediaReplay(
                            $existingBeforeWrite,
                            $productId,
                            $payloadHash,
                            $action,
                            $stagedPathHash,
                        );

                        return $lockedBeforeWrite->fresh();
                    }
                }

                $written = $write();
                $productId = (int) $written->getKey();
                // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
                $locked = Product::withoutGlobalScopes()
                    ->whereKey($productId)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = DB::table('product_media_operations')
                    ->where('tenant_id', $tenantId)
                    ->where('operation_key_hash', $operationHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertCompatibleMediaReplay(
                        $existing,
                        $productId,
                        $payloadHash,
                        $action,
                        $stagedPathHash,
                    );

                    return $locked->fresh();
                }

                $previousPath = is_string($locked->image_path) ? $locked->image_path : null;
                $sourceDigest = null;

                if ($action === 'remove') {
                    $updated = $this->writer->update($tenantId, $productId, ['image_path' => null]);
                } else {
                    if ($stagedPath === null) {
                        throw new \LogicException('A replacement media operation requires a staged path.');
                    }

                    $promotion = $this->imageStorage->promote($tenantId, $productId, $operationKey, $stagedPath);
                    $publishedPath = $promotion->publishedPath;
                    $sourceDigest = $promotion->sourceDigest;
                    $updated = $this->writer->update($tenantId, $productId, [
                        'image_path' => $publishedPath,
                    ]);
                }

                DB::table('product_media_operations')->insert([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'operation_key_hash' => $operationHash,
                    'payload_hash' => $payloadHash,
                    'staged_path_hash' => $stagedPathHash,
                    'source_digest' => $sourceDigest,
                    'action' => $action,
                    'published_path' => $publishedPath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($stagedPath !== null) {
                    $this->cleanup->enqueue($tenantId, 'local', $stagedPath);
                }
                if ($previousPath !== null && $previousPath !== $publishedPath) {
                    $this->enqueueCanonicalPublishedCleanup($tenantId, $productId, $previousPath);
                }

                return $updated;
            });
        } catch (Throwable $exception) {
            if ($stagedPath !== null) {
                $this->enqueueCleanupWithoutMasking($tenantId, 'local', $stagedPath, $exception);
            }
            if ($publishedPath !== null && $productId !== null) {
                $this->enqueueCleanupWithoutMasking($tenantId, 'public', $publishedPath, $exception, $productId);
            }
            $this->drainCleanupWithoutMasking($exception);

            throw $exception;
        }

        $this->drainCleanupWithoutMasking();

        return $product;
    }

    private function assertCompatibleMediaReplay(
        object $existing,
        int $productId,
        string $payloadHash,
        string $action,
        ?string $stagedPathHash,
    ): void {
        if (
            (int) $existing->product_id !== $productId
            || ! hash_equals((string) $existing->payload_hash, $payloadHash)
            || (string) $existing->action !== $action
            || (string) ($existing->staged_path_hash ?? '') !== (string) ($stagedPathHash ?? '')
        ) {
            throw new \LogicException('Product media idempotency key was reused with an incompatible payload.');
        }
    }

    /** @param array<string, mixed> $data */
    private function mediaPayloadHash(array $data): string
    {
        $mediaPayload = $data;
        unset($mediaPayload['tenant_id']);

        return hash('sha256', serialize($mediaPayload));
    }

    private function enqueueCanonicalPublishedCleanup(string $tenantId, int $productId, string $path): void
    {
        try {
            $this->cleanup->enqueue($tenantId, 'public', $path, $productId);
        } catch (InvalidArgumentException) {
            // Legacy non-canonical paths are retained for the orphan reconciler;
            // never risk deleting a path outside the tenant/product namespace.
        }
    }

    private function enqueueCleanupWithoutMasking(
        string $tenantId,
        string $disk,
        string $path,
        Throwable $primary,
        ?int $productId = null,
    ): void {
        try {
            $this->cleanup->enqueue($tenantId, $disk, $path, $productId);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
            report($primary);
        }
    }

    private function drainCleanupWithoutMasking(?Throwable $primary = null): void
    {
        try {
            $this->cleanup->drain(25);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
            if ($primary !== null) {
                report($primary);
            }
        }
    }
}
