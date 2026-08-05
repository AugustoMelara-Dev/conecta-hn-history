<?php

declare(strict_types=1);

namespace App\Infrastructure\Crm;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: responder queries are constrained by the authenticated tenant.

use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Crm\Commands\CreateOpportunityCommand;
use App\Application\Crm\Commands\MoveOpportunityStageCommand;
use App\Application\Crm\Commands\UpdateOpportunityCommand;
use App\Application\Imports\OperationalImportService;
use App\Application\Markets\MarketContext;
use App\Application\Markets\TenantMarketResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ConvertLeadToCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\DeleteCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\ExportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ImportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ListCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\MoveCrmDealStageRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmDealRequest;
use App\Http\Resources\CrmDealResource;
use App\Models\CatalogImportItem;
use App\Models\CrmDeal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HttpCrmDealResponder extends Controller
{
    public function __construct(
        private readonly CanonicalOpportunityWriter $writer,
        private readonly TenantMarketResolver $marketResolver,
        private readonly OperationalImportService $imports,
    ) {}

    public function index(ListCrmDealsRequest $request): AnonymousResourceCollection
    {
        $tenant = $request->tenantForRequest();
        $validated = $request->validated();

        $query = CrmDeal::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->with(['customerProfile', 'lead', 'stage'])
            ->latest('updated_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['stage_id'])) {
            $query->where('crm_stage_id', (int) $validated['stage_id']);
        }

        if (isset($validated['owner_user_id'])) {
            $query->where('owner_user_id', (string) $validated['owner_user_id']);
        }

        return CrmDealResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function store(StoreCrmDealRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $data = $request->validated();
        $deal = $this->writer->create(new CreateOpportunityCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $request->user()->getKey(),
            idempotencyKey: $this->idempotencyKey($request->header('Idempotency-Key'), 'crm-api:create'),
            title: (string) $data['title'],
            valueMinor: $this->minorFromDecimal($tenant, $data['value'] ?? 0, $data['currency'] ?? null),
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            pipelineId: (int) $data['pipeline_id'],
            stageId: (int) $data['stage_id'],
            customerProfileId: isset($data['customer_profile_id']) ? (int) $data['customer_profile_id'] : null,
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            ownerUserId: isset($data['owner_user_id']) ? (string) $data['owner_user_id'] : null,
            locationId: isset($data['location_id']) ? (string) $data['location_id'] : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
            expectedCloseDate: isset($data['expected_close_at']) ? (string) $data['expected_close_at'] : null,
            lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        ))->deal;

        return (new CrmDealResource($deal->load(['customerProfile', 'lead', 'stage'])))
            ->response()
            ->setStatusCode(201);
    }

    public function import(ImportCrmDealsRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $rows = $request->validated('deals');
        $headers = [
            'title', 'value_minor', 'currency', 'pipeline_id', 'stage_id',
            'customer_profile_id', 'lead_id', 'owner_user_id', 'location_id',
            'source', 'expected_close_date', 'lost_reason', 'notes',
        ];
        $temporary = tempnam(sys_get_temp_dir(), 'dovra-crm-import-');
        if ($temporary === false) {
            throw new \RuntimeException('CRM import staging file could not be created.');
        }
        $handle = fopen($temporary, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('CRM import staging file could not be opened.');
        }
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $this->safeCsv((string) $row['title']),
                $this->minorFromDecimal($tenant, $row['value'] ?? 0, $row['currency'] ?? null),
                strtoupper((string) ($row['currency'] ?? $this->marketResolver->resolve($tenant)->currencyCode)),
                $row['pipeline_id'] ?? null,
                $row['stage_id'] ?? null,
                $row['customer_profile_id'] ?? null,
                $row['lead_id'] ?? null,
                $row['owner_user_id'] ?? null,
                $row['location_id'] ?? null,
                $row['source'] ?? 'crm_import',
                $row['expected_close_at'] ?? null,
                $row['lost_reason'] ?? null,
                $row['notes'] ?? null,
            ]);
        }
        fclose($handle);
        try {
            $batchId = $this->imports->stage(
                (string) $tenant->getKey(),
                (string) $request->user()->getKey(),
                new UploadedFile($temporary, 'crm-deals.csv', 'text/csv', null, true),
                'opportunity',
                $this->idempotencyKey(
                    $request->header('Idempotency-Key')
                        ?? 'crm-import:'.substr(hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 0, 24),
                    'crm-import',
                ),
                sourceOrigin: 'crm_json',
            );
        } finally {
            @unlink($temporary);
        }
        $this->imports->process((string) $tenant->getKey(), $batchId);
        $reviewable = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('batch_id', $batchId)
            ->where('status', 'needs_review')
            ->pluck('id')
            ->mapWithKeys(fn (string $id): array => [$id => 'approve'])
            ->all();
        $this->imports->review(
            (string) $tenant->getKey(),
            (string) $request->user()->getKey(),
            $batchId,
            $reviewable,
        );
        $report = $this->imports->publish(
            (string) $tenant->getKey(),
            (string) $request->user()->getKey(),
            $batchId,
        );
        $dealIds = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('batch_id', $batchId)
            ->where('status', 'published')
            ->pluck('aggregate_id');
        $created = CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('id', $dealIds)
            ->with(['customerProfile', 'lead', 'stage'])
            ->get();

        return response()->json([
            'message' => 'Oportunidades importadas.',
            'created_count' => $created->count(),
            'failed_count' => $report->failed,
            'data' => CrmDealResource::collection($created),
        ], 201);
    }

    public function export(ExportCrmDealsRequest $request): StreamedResponse
    {
        $tenant = $request->tenantForRequest();
        $validated = $request->validated();
        $filename = 'crm-deals-tenant-'.$tenant->getKey().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($tenant, $validated): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'title',
                'status',
                'pipeline',
                'stage',
                'value',
                'currency',
                'expected_close_at',
                'closed_at',
                'source',
                'customer',
                'lead',
            ]);

            $this->exportQuery($tenant, $validated)
                ->chunkById(100, function ($deals) use ($handle): void {
                    foreach ($deals as $deal) {
                        fputcsv($handle, [
                            $deal->getKey(),
                            $this->safeCsv((string) $deal->title),
                            $deal->status,
                            $this->safeCsv((string) ($deal->pipeline?->name ?? '')),
                            $this->safeCsv((string) ($deal->stage?->name ?? '')),
                            $deal->value,
                            $deal->currency,
                            $deal->expected_close_at?->toDateString(),
                            $deal->closed_at?->toIso8601String(),
                            $this->safeCsv((string) ($deal->source ?? '')),
                            $this->safeCsv((string) ($deal->customerProfile?->name ?? '')),
                            $this->safeCsv((string) ($deal->lead?->name ?? '')),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function convertLead(ConvertLeadToCrmDealRequest $request, int $lead): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $data = $request->validated();
        $overrides = array_filter([
            'pipeline_id' => $data['pipeline_id'] ?? null,
            'stage_id' => $data['stage_id'] ?? null,
            'title' => $data['title'] ?? null,
            'value_minor' => array_key_exists('value', $data)
                ? $this->minorFromDecimal($tenant, $data['value'], $data['currency'] ?? null)
                : null,
            'currency' => $data['currency'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'expected_close_date' => $data['expected_close_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $result = $this->writer->convertLead(
            (string) $tenant->getKey(),
            (string) $request->user()->getKey(),
            $lead,
            $this->idempotencyKey($request->header('Idempotency-Key'), "crm-lead:{$lead}"),
            $overrides,
        );

        return (new CrmDealResource($result->deal->load(['customerProfile', 'lead', 'stage'])))
            ->response()
            ->setStatusCode($result->replayed ? 200 : 201);
    }

    public function update(UpdateCrmDealRequest $request, int $deal): CrmDealResource
    {
        $tenant = $request->tenantForRequest();
        $data = $request->validated();
        $result = $this->writer->update(new UpdateOpportunityCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $request->user()->getKey(),
            dealId: $deal,
            title: isset($data['title']) ? (string) $data['title'] : null,
            valueMinor: array_key_exists('value', $data) && $data['value'] !== null
                ? $this->minorFromDecimal($tenant, $data['value'], $data['currency'] ?? null)
                : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            customerProfileId: isset($data['customer_profile_id']) ? (int) $data['customer_profile_id'] : null,
            customerProfileSpecified: array_key_exists('customer_profile_id', $data),
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            leadSpecified: array_key_exists('lead_id', $data),
            ownerUserId: isset($data['owner_user_id']) ? (string) $data['owner_user_id'] : null,
            ownerSpecified: array_key_exists('owner_user_id', $data),
            locationId: isset($data['location_id']) ? (string) $data['location_id'] : null,
            locationSpecified: array_key_exists('location_id', $data),
            source: isset($data['source']) ? (string) $data['source'] : null,
            sourceSpecified: array_key_exists('source', $data),
            expectedCloseDate: isset($data['expected_close_at']) ? (string) $data['expected_close_at'] : null,
            expectedCloseDateSpecified: array_key_exists('expected_close_at', $data),
            lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            lostReasonSpecified: array_key_exists('lost_reason', $data),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            notesSpecified: array_key_exists('notes', $data),
        ));
        if (isset($data['stage_id'])) {
            $result = $this->writer->moveStage(new MoveOpportunityStageCommand(
                tenantId: (string) $tenant->getKey(),
                actorId: (string) $request->user()->getKey(),
                dealId: $deal,
                stageId: (int) $data['stage_id'],
                lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            ));
        }

        return new CrmDealResource($result->deal->refresh()->load(['customerProfile', 'lead', 'stage']));
    }

    public function moveStage(MoveCrmDealStageRequest $request, int $deal): CrmDealResource
    {
        $tenant = $request->tenantForRequest();
        $result = $this->writer->moveStage(new MoveOpportunityStageCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $request->user()->getKey(),
            dealId: $deal,
            stageId: (int) $request->validated('stage_id'),
        ));

        return new CrmDealResource($result->deal->refresh()->load(['customerProfile', 'lead', 'stage']));
    }

    public function destroy(DeleteCrmDealRequest $request, int $deal): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $this->writer->archive(
            (string) $tenant->getKey(),
            (string) $request->user()->getKey(),
            $deal,
        );

        return response()->json([
            'message' => 'Deal eliminado.',
        ]);
    }

    private function idempotencyKey(?string $provided, string $prefix): string
    {
        $provided = trim((string) $provided);

        return $provided === '' ? $prefix.':'.Str::uuid() : mb_substr($provided, 0, 160);
    }

    private function minorFromDecimal(Tenant $tenant, mixed $value, mixed $currency): int
    {
        $market = $this->marketResolver->resolveOrNull($tenant);
        if (! $market instanceof MarketContext) {
            throw ValidationException::withMessages([
                'market' => ['Tenant market context is required for CRM money.'],
            ]);
        }
        $currencyCode = strtoupper((string) ($currency ?: $market->currencyCode));
        $minorUnit = DB::table('currencies')->where('code', $currencyCode)->value('minor_unit');
        if ($minorUnit === null) {
            throw new \InvalidArgumentException("Currency [{$currencyCode}] is not configured.");
        }

        return (int) round((float) $value * (10 ** (int) $minorUnit));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportQuery(Tenant $tenant, array $filters): Builder
    {
        return CrmDeal::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->with(['pipeline', 'stage', 'customerProfile', 'lead'])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['pipeline_id']), fn ($query) => $query->where('crm_pipeline_id', (int) $filters['pipeline_id']))
            ->when(isset($filters['stage_id']), fn ($query) => $query->where('crm_stage_id', (int) $filters['stage_id']))
            ->orderBy('id');
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
