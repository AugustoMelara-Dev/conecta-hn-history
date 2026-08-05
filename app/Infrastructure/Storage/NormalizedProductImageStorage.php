<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Products\ProductImagePromotion;
use App\Application\Products\ProductImageStorageInterface;
use App\Application\Products\ProductMediaCleanupQueue;
use App\Infrastructure\Security\SafeCatalogImageNormalizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class NormalizedProductImageStorage implements ProductImageStorageInterface
{
    private const UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly SafeCatalogImageNormalizer $normalizer,
        private readonly ProductMediaCleanupQueue $cleanup,
    ) {}

    public function stagedPath(string $tenantId, mixed $state): ?string
    {
        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        $paths = is_array($state) ? array_values($state) : [$state];
        if (count($paths) !== 1 || ! is_string($paths[0])) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['Selecciona una sola imagen de producto.'],
            ]);
        }

        $path = str_replace('\\', '/', trim($paths[0]));
        $this->assertStagedOwnership($tenantId, $path);

        return $path;
    }

    public function promote(
        string $tenantId,
        int $productId,
        string $operationKey,
        string $stagedPath,
    ): ProductImagePromotion {
        $this->assertStagedOwnership($tenantId, $stagedPath);
        $private = Storage::disk('local');
        $public = Storage::disk('public');

        if (! $private->exists($stagedPath)) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['La imagen temporal ya no esta disponible; vuelve a seleccionarla.'],
            ]);
        }

        $size = $private->size($stagedPath);
        if ($size < 1 || $size > SafeCatalogImageNormalizer::MAX_INPUT_BYTES) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['La imagen excede el limite seguro de 10 MB.'],
            ]);
        }

        $contents = $private->get($stagedPath);
        if (strlen($contents) > SafeCatalogImageNormalizer::MAX_INPUT_BYTES) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['La imagen excede el limite seguro de 10 MB.'],
            ]);
        }

        try {
            $normalized = $this->normalizer->normalize($contents);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'pending_image_path' => [$exception->getMessage()],
            ]);
        }

        $extension = match ($normalized['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Normalized product image has an unsupported MIME type.'),
        };
        $fileId = $this->deterministicUuid($tenantId, $productId, $operationKey);
        $publishedPath = "tenants/{$tenantId}/products/{$productId}/{$fileId}.{$extension}";
        $this->assertPublishedOwnership($tenantId, $productId, $publishedPath);

        try {
            if (! $public->put($publishedPath, $normalized['contents'])) {
                throw new RuntimeException('The normalized product image could not be published.');
            }
        } catch (Throwable $exception) {
            try {
                $this->cleanup->enqueue($tenantId, 'public', $publishedPath, $productId);
                $this->cleanup->drain(1);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }

        return new ProductImagePromotion(
            publishedPath: $publishedPath,
            sourceDigest: hash('sha256', $contents),
        );
    }

    public function discardStaged(string $tenantId, string $stagedPath): void
    {
        $this->assertStagedOwnership($tenantId, $stagedPath);
        Storage::disk('local')->delete($stagedPath);
    }

    public function discardPublished(string $tenantId, int $productId, ?string $publishedPath): void
    {
        if ($publishedPath === null || $publishedPath === '') {
            return;
        }

        $this->assertPublishedOwnership($tenantId, $productId, $publishedPath);
        Storage::disk('public')->delete($publishedPath);
    }

    private function assertStagedOwnership(string $tenantId, string $path): void
    {
        $tenant = preg_quote($tenantId, '#');
        $valid = preg_match("#^manual-product-staging/{$tenant}/[A-Za-z0-9][A-Za-z0-9._-]{0,127}$#D", $path) === 1;

        if (! $valid || str_contains($path, '..') || preg_match('/[[:cntrl:]]/', $path) === 1) {
            throw ValidationException::withMessages([
                'pending_image_path' => ['La imagen temporal no pertenece a este negocio.'],
            ]);
        }
    }

    private function assertPublishedOwnership(string $tenantId, int $productId, string $path): void
    {
        $tenant = preg_quote($tenantId, '#');
        $uuid = self::UUID_PATTERN;
        $valid = preg_match("#^tenants/{$tenant}/products/{$productId}/{$uuid}\\.(?:jpg|png|webp)$#D", $path) === 1;

        if (! $valid || str_contains($path, '..') || preg_match('/[[:cntrl:]]/', $path) === 1) {
            throw new InvalidArgumentException('Published product image path is not canonical for this tenant and product.');
        }
    }

    private function deterministicUuid(string $tenantId, int $productId, string $operationKey): string
    {
        $hex = hash('sha256', $tenantId."\0".$productId."\0".$operationKey);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
