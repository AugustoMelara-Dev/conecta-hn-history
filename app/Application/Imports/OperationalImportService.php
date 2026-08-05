<?php

declare(strict_types=1);

namespace App\Application\Imports;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: batch and aggregate access is constrained by the authenticated tenant.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Crm\Commands\CreateOpportunityCommand;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Products\CanonicalProductWriter;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Application\ServiceCatalog\CanonicalServiceWriter;
use App\Application\ServiceCatalog\Commands\CreateServiceCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Jobs\ProcessOperationalImport;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final readonly class OperationalImportService
{
    private const MAX_FILE_BYTES = 10_485_760;

    private const MAX_ROW_BYTES = 32_768;

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'product' => ['name', 'price_minor', 'currency', 'stock_quantity', 'published'],
        'service' => [
            'name', 'description', 'price_minor', 'currency', 'duration_minutes',
            'location_id', 'timezone', 'published',
        ],
        'opportunity' => [
            'title', 'value_minor', 'currency', 'pipeline_id', 'stage_id',
            'customer_profile_id', 'lead_id', 'owner_user_id', 'location_id',
            'source', 'expected_close_date', 'lost_reason', 'notes',
        ],
    ];

    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private TenantMarketResolver $marketResolver,
        private CanonicalProductWriter $products,
        private CanonicalServiceWriter $services,
        private CanonicalOpportunityWriter $opportunities,
    ) {}

    public function stage(
        string $tenantId,
        string $actorId,
        UploadedFile $file,
        string $kind,
        string $idempotencyKey,
        string $sourceOrigin = 'upload',
    ): string {
        $this->assertKind($kind);
        if (! in_array($sourceOrigin, ['upload', 'crm_json'], true)) {
            throw ValidationException::withMessages([
                'source_origin' => ['Unsupported operational import source origin.'],
            ]);
        }
        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 160) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Import idempotency key must contain 1 to 160 characters.'],
            ]);
        }
        [$tenant] = $this->authority(
            $tenantId,
            $actorId,
            $kind === 'opportunity'
                ? Capability::CRM_PIPELINE_MANAGEMENT
                : Capability::CATALOG_BASIC,
            $kind === 'opportunity'
                ? Permission::CRM_DEALS_CREATE
                : Permission::CATALOG_CREATE,
        );
        $bytes = (string) file_get_contents($file->getRealPath());
        $this->assertSource($file, $bytes);
        $sha = hash('sha256', $bytes);
        $payloadHash = hash('sha256', implode('|', [
            $kind,
            $sourceOrigin,
            $file->getClientOriginalName(),
            $sha,
        ]));
        $existing = CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing instanceof CatalogImportBatch) {
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['Import idempotency key was reused with another source.'],
                ]);
            }

            return (string) $existing->getKey();
        }
        $batchId = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            .'.'.strtolower($file->getClientOriginalExtension());
        $path = "operational-imports/{$tenantId}/{$batchId}/{$safeName}";
        Storage::disk('local')->put($path, $bytes);
        try {
            $batch = new CatalogImportBatch;
            $batch->forceFill([
                'id' => $batchId,
                'tenant_id' => $tenant->getKey(),
                'created_by' => $actorId,
                'kind' => $kind,
                'source_origin' => $sourceOrigin,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'correlation_id' => (string) Str::uuid(),
                'source_disk' => 'local',
                'source_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => strlen($bytes),
                'sha256' => $sha,
                'status' => CatalogImportStatus::Queued,
            ])->save();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        ProcessOperationalImport::dispatchAfterResponse(
            tenantId: $tenantId,
            actorId: $actorId,
            batchId: $batchId,
            correlationId: (string) $batch->correlation_id,
            idempotencyKey: $idempotencyKey,
        );

        return $batchId;
    }

    public function process(string $tenantId, string $batchId): OperationalImportReport
    {
        try {
            return DB::transaction(function () use ($tenantId, $batchId): OperationalImportReport {
                $batch = CatalogImportBatch::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($batchId);
                if (CatalogImportItem::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('batch_id', $batchId)
                    ->exists()) {
                    return $this->report($batch);
                }
                $batch->forceFill(['status' => CatalogImportStatus::Processing])->save();
                $contents = Storage::disk((string) $batch->source_disk)
                    ->get((string) $batch->source_path);
                $rows = $this->parse($batch, $contents);
                foreach ($rows as $row) {
                    $item = new CatalogImportItem;
                    $item->forceFill([
                        'tenant_id' => $tenantId,
                        'batch_id' => $batch->getKey(),
                        'row_number' => $row->rowNumber,
                        'aggregate_type' => $batch->kind,
                        'status' => $row->isValid()
                            ? CatalogImportStatus::NeedsReview
                            : CatalogImportStatus::Failed,
                        'source_disk' => $batch->source_disk,
                        'source_path' => $batch->source_path,
                        'original_name' => $batch->original_name.'#'.$row->rowNumber,
                        'mime_type' => $batch->mime_type,
                        'size_bytes' => strlen(json_encode($row->source, JSON_THROW_ON_ERROR)),
                        'sha256' => hash('sha256', json_encode($row->source, JSON_THROW_ON_ERROR)),
                        'error_code' => $row->errors[0] ?? null,
                        'error_message' => $row->errors === [] ? null : implode(', ', $row->errors),
                        'source_payload' => $row->source,
                        'normalized_payload' => $row->normalized,
                        'validation_errors' => $row->errors,
                        'processed_at' => now(),
                    ])->save();
                }
                $failed = count(array_filter(
                    $rows,
                    fn (OperationalImportRow $row): bool => ! $row->isValid(),
                ));
                $batch->forceFill([
                    'status' => $failed === count($rows)
                        ? CatalogImportStatus::Failed
                        : CatalogImportStatus::NeedsReview,
                    'total_items' => count($rows),
                    'processed_items' => count($rows),
                    'failed_items' => $failed,
                    'completed_at' => now(),
                ])->save();

                return $this->report($batch->fresh());
            }, 3);
        } catch (ValidationException $exception) {
            CatalogImportBatch::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($batchId)
                ->update([
                    'status' => CatalogImportStatus::Failed->value,
                    'completed_at' => now(),
                ]);

            throw $exception;
        }
    }

    /** @param array<string, string> $decisions */
    public function review(
        string $tenantId,
        string $actorId,
        string $batchId,
        array $decisions,
    ): OperationalImportReport {
        $reviewBatch = CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($batchId);
        $this->authority(
            $tenantId,
            $actorId,
            $reviewBatch->kind === 'opportunity'
                ? Capability::CRM_PIPELINE_MANAGEMENT
                : Capability::CATALOG_BASIC,
            $reviewBatch->kind === 'opportunity'
                ? Permission::CRM_DEALS_UPDATE
                : Permission::CATALOG_UPDATE,
        );

        return DB::transaction(function () use ($tenantId, $actorId, $batchId, $decisions): OperationalImportReport {
            $batch = CatalogImportBatch::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($batchId);
            foreach ($decisions as $itemId => $decision) {
                $item = CatalogImportItem::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('batch_id', $batchId)
                    ->lockForUpdate()
                    ->findOrFail($itemId);
                if ($item->status !== CatalogImportStatus::NeedsReview) {
                    continue;
                }
                if (! in_array($decision, ['approve', 'reject'], true)) {
                    throw ValidationException::withMessages([
                        "decisions.{$itemId}" => ['Decision must be approve or reject.'],
                    ]);
                }
                $item->forceFill([
                    'status' => $decision === 'approve'
                        ? CatalogImportStatus::Approved
                        : CatalogImportStatus::Rejected,
                    'reviewed_by' => $actorId,
                    'reviewed_at' => now(),
                ])->save();
            }

            return $this->report($batch->fresh());
        }, 3);
    }

    public function publish(
        string $tenantId,
        string $actorId,
        string $batchId,
    ): OperationalImportReport {
        $batch = CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($batchId);
        $this->authority(
            $tenantId,
            $actorId,
            $batch->kind === 'opportunity'
                ? Capability::CRM_PIPELINE_MANAGEMENT
                : Capability::CATALOG_BASIC,
            $batch->kind === 'opportunity'
                ? Permission::CRM_DEALS_CREATE
                : Permission::CATALOG_PUBLISH,
        );
        $items = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_id', $batchId)
            ->where('status', CatalogImportStatus::Approved->value)
            ->orderBy('row_number')
            ->get();
        foreach ($items as $item) {
            try {
                $aggregate = $this->publishItem($batch, $item, $actorId);
                $item->forceFill([
                    'status' => CatalogImportStatus::Published,
                    'aggregate_id' => (string) $aggregate->getKey(),
                    'product_id' => $batch->kind === 'product' ? $aggregate->getKey() : null,
                    'published_at' => now(),
                    'error_code' => null,
                    'error_message' => null,
                ])->save();
            } catch (\Throwable $exception) {
                $item->forceFill([
                    'status' => CatalogImportStatus::Failed,
                    'error_code' => 'publication_failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();
            }
        }
        $remaining = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('batch_id', $batchId)
            ->whereIn('status', [
                CatalogImportStatus::NeedsReview->value,
                CatalogImportStatus::Approved->value,
            ])
            ->exists();
        $batch->forceFill([
            'status' => $remaining
                ? CatalogImportStatus::NeedsReview
                : CatalogImportStatus::Published,
            'failed_items' => CatalogImportItem::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('batch_id', $batchId)
                ->where('status', CatalogImportStatus::Failed->value)
                ->count(),
            'completed_at' => now(),
        ])->save();

        return $this->report($batch->fresh());
    }

    /** @return array{Tenant, User} */
    private function authority(
        string $tenantId,
        string $actorId,
        Capability $capability,
        Permission $permission,
    ): array {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows($actor, $tenant, $capability, $permission)) {
            throw new AuthorizationException('Actor cannot manage operational imports for this tenant.');
        }

        return [$tenant, $actor];
    }

    private function assertKind(string $kind): void
    {
        if (! array_key_exists($kind, self::COLUMNS)) {
            throw ValidationException::withMessages(['kind' => ['Unsupported operational import kind.']]);
        }
    }

    private function assertSource(UploadedFile $file, string $bytes): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (strlen($bytes) === 0 || strlen($bytes) > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['file' => ['Import file size is invalid.']]);
        }
        if ($extension === 'csv') {
            if (! in_array($file->getClientMimeType(), [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
            ], true)) {
                throw ValidationException::withMessages(['file' => ['CSV MIME type is unsupported.']]);
            }
            if (! mb_check_encoding($bytes, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => ['CSV must use UTF-8 encoding.']]);
            }

            return;
        }
        if ($extension === 'xlsx'
            && in_array($file->getClientMimeType(), [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ], true)
            && str_starts_with($bytes, "PK\x03\x04")) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => ['Import extension, MIME, or file signature is unsupported.'],
        ]);
    }

    /** @return list<OperationalImportRow> */
    private function parse(CatalogImportBatch $batch, string $contents): array
    {
        $extension = strtolower(pathinfo((string) $batch->original_name, PATHINFO_EXTENSION));
        $sourceRows = [];
        if ($extension === 'csv') {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            while (($values = fgetcsv($stream)) !== false) {
                $sourceRows[] = $values;
            }
            fclose($stream);
        } elseif ($extension === 'xlsx') {
            $sourceRows = IOFactory::load(Storage::disk((string) $batch->source_disk)
                ->path((string) $batch->source_path))
                ->getActiveSheet()
                ->toArray(null, false, false, false);
        } else {
            throw ValidationException::withMessages(['file' => ['Unsupported staged import source.']]);
        }
        $headers = array_map(
            static fn (mixed $value): string => trim(mb_strtolower((string) $value)),
            array_shift($sourceRows) ?: [],
        );
        $unknown = array_diff($headers, self::COLUMNS[(string) $batch->kind]);
        if ($headers === [] || $unknown !== []) {
            throw ValidationException::withMessages([
                'columns' => ['Unknown import columns: '.implode(', ', $unknown).'.'],
            ]);
        }
        $rows = [];
        foreach ($sourceRows as $offset => $values) {
            $rowNumber = $offset + 2;
            $source = [];
            foreach ($headers as $index => $header) {
                $source[$header] = isset($values[$index]) ? trim((string) $values[$index]) : null;
            }
            $rows[] = $this->normalizeRow($batch, $rowNumber, $source);
        }

        return $rows;
    }

    /** @param array<string, string|null> $source */
    private function normalizeRow(
        CatalogImportBatch $batch,
        int $rowNumber,
        array $source,
    ): OperationalImportRow {
        $encoded = json_encode($source, JSON_THROW_ON_ERROR);
        $errors = [];
        if (strlen($encoded) > self::MAX_ROW_BYTES) {
            $errors[] = 'row_too_large';
        }
        foreach ($source as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = ltrim($value);
            $formulaLiteral = preg_match("/^'[=+\\-@]/", $trimmed) === 1;
            $formula = preg_match('/^[=+@]/', $trimmed) === 1
                || (str_starts_with($trimmed, '-') && ! is_numeric($trimmed));
            if ($formula || ($formulaLiteral && $batch->source_origin !== 'crm_json')) {
                $errors[] = 'formula_injection';
                break;
            }
        }
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($batch->tenant_id);
        $market = $this->marketResolver->resolve($tenant);
        if (strtoupper((string) ($source['currency'] ?? '')) !== strtoupper($market->currencyCode)) {
            $errors[] = 'currency_mismatch';
        }
        $normalized = match ((string) $batch->kind) {
            'product' => $this->normalizeProduct($source, $errors),
            'service' => $this->normalizeService((string) $batch->tenant_id, $source, $errors),
            'opportunity' => $this->normalizeOpportunity(
                (string) $batch->tenant_id,
                (string) $batch->source_origin,
                $source,
                $errors,
            ),
            default => throw new \LogicException('Unsupported staged import kind.'),
        };

        return new OperationalImportRow(
            $rowNumber,
            $source,
            $normalized,
            array_values(array_unique($errors)),
        );
    }

    /**
     * @param  array<string, string|null>  $source
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $source, array &$errors): array
    {
        $name = trim((string) ($source['name'] ?? ''));
        $price = filter_var($source['price_minor'] ?? null, FILTER_VALIDATE_INT);
        $stock = filter_var($source['stock_quantity'] ?? 0, FILTER_VALIDATE_INT);
        if ($name === '' || $price === false || $price < 0 || $stock === false || $stock < 0) {
            $errors[] = 'invalid_product_row';
        }

        return [
            'name' => $name,
            'price_minor' => (int) $price,
            'stock_quantity' => (int) $stock,
            'published' => filter_var(
                $source['published'] ?? false,
                FILTER_VALIDATE_BOOL,
            ),
        ];
    }

    /**
     * @param  array<string, string|null>  $source
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function normalizeService(string $tenantId, array $source, array &$errors): array
    {
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($source['location_id'] ?? '');
        if (! $location instanceof Location) {
            $errors[] = 'location_mismatch';
        } elseif (($source['timezone'] ?? $location->timezone_name) !== $location->timezone_name) {
            $errors[] = 'timezone_mismatch';
        }
        $price = filter_var($source['price_minor'] ?? null, FILTER_VALIDATE_INT);
        $duration = filter_var($source['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
        if (trim((string) ($source['name'] ?? '')) === ''
            || $price === false
            || $price < 0
            || $duration === false
            || $duration < 1) {
            $errors[] = 'invalid_service_row';
        }

        return [
            'name' => trim((string) ($source['name'] ?? '')),
            'description' => $source['description'] ?? null,
            'price_minor' => (int) $price,
            'duration_minutes' => (int) $duration,
            'location_id' => $location?->getKey(),
            'published' => filter_var($source['published'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param  array<string, string|null>  $source
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function normalizeOpportunity(
        string $tenantId,
        string $sourceOrigin,
        array $source,
        array &$errors,
    ): array {
        $title = trim((string) ($source['title'] ?? ''));
        if ($sourceOrigin === 'crm_json'
            && preg_match("/^'[=+\\-@]/", $title) === 1) {
            $title = mb_substr($title, 1);
        }
        $locationId = $source['location_id'] ?? null;
        if ($locationId !== null && $locationId !== '' && ! Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($locationId)
            ->exists()) {
            $errors[] = 'location_mismatch';
        }
        $value = filter_var($source['value_minor'] ?? 0, FILTER_VALIDATE_INT);
        if ($title === '' || $value === false || $value < 0) {
            $errors[] = 'invalid_opportunity_row';
        }

        return [
            'title' => $title,
            'value_minor' => (int) $value,
            'pipeline_id' => filled($source['pipeline_id'] ?? null) ? (int) $source['pipeline_id'] : null,
            'stage_id' => filled($source['stage_id'] ?? null) ? (int) $source['stage_id'] : null,
            'customer_profile_id' => filled($source['customer_profile_id'] ?? null) ? (int) $source['customer_profile_id'] : null,
            'lead_id' => filled($source['lead_id'] ?? null) ? (int) $source['lead_id'] : null,
            'owner_user_id' => filled($source['owner_user_id'] ?? null) ? $source['owner_user_id'] : null,
            'location_id' => filled($locationId) ? $locationId : null,
            'source' => $source['source'] ?? 'operational_import',
            'expected_close_date' => ($source['expected_close_date'] ?? null) ?: null,
            'lost_reason' => ($source['lost_reason'] ?? null) ?: null,
            'notes' => ($source['notes'] ?? null) ?: null,
        ];
    }

    private function publishItem(
        CatalogImportBatch $batch,
        CatalogImportItem $item,
        string $actorId,
    ): object {
        $payload = (array) $item->normalized_payload;
        $key = 'operational-import:'.$batch->getKey().':'.$item->row_number;

        return match ((string) $batch->kind) {
            'product' => $this->products->create(new CreateCanonicalProductCommand(
                tenantId: (string) $batch->tenant_id,
                idempotencyKey: $key,
                name: (string) $payload['name'],
                priceMinor: (int) $payload['price_minor'],
                initialStockQuantity: (int) $payload['stock_quantity'],
                published: (bool) $payload['published'],
            )),
            'service' => $this->services->create(new CreateServiceCommand(
                tenantId: (string) $batch->tenant_id,
                actorId: $actorId,
                idempotencyKey: $key,
                name: (string) $payload['name'],
                priceMinor: (int) $payload['price_minor'],
                currency: (string) $this->marketResolver
                    ->resolve(Tenant::query()->withoutGlobalScopes()->findOrFail($batch->tenant_id))
                    ->currencyCode,
                durationMinutes: (int) $payload['duration_minutes'],
                locationIds: [(string) $payload['location_id']],
                description: $payload['description'],
                published: (bool) $payload['published'],
            )),
            'opportunity' => $this->opportunities->create(new CreateOpportunityCommand(
                tenantId: (string) $batch->tenant_id,
                actorId: $actorId,
                idempotencyKey: $key,
                title: (string) $payload['title'],
                valueMinor: (int) $payload['value_minor'],
                pipelineId: $payload['pipeline_id'],
                stageId: $payload['stage_id'],
                customerProfileId: $payload['customer_profile_id'],
                leadId: $payload['lead_id'],
                ownerUserId: $payload['owner_user_id'],
                locationId: $payload['location_id'],
                source: (string) $payload['source'],
                expectedCloseDate: $payload['expected_close_date'],
                lostReason: $payload['lost_reason'],
                notes: $payload['notes'],
            ))->deal,
            default => throw new ModelNotFoundException('Unsupported operational import kind.'),
        };
    }

    private function report(CatalogImportBatch $batch): OperationalImportReport
    {
        $counts = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $batch->tenant_id)
            ->where('batch_id', $batch->getKey())
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return new OperationalImportReport(
            batchId: (string) $batch->getKey(),
            total: (int) $counts->sum(),
            approved: (int) ($counts[CatalogImportStatus::Approved->value] ?? 0),
            rejected: (int) ($counts[CatalogImportStatus::Rejected->value] ?? 0),
            failed: (int) ($counts[CatalogImportStatus::Failed->value] ?? 0),
            published: (int) ($counts[CatalogImportStatus::Published->value] ?? 0),
        );
    }
}
