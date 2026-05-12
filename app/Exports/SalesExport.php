<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Shared\ValueObjects\TenantId;
use App\Events\Audit\SalesExportCreated;
use App\Exports\Concerns\SanitizesOutput;
use App\Exports\Criteria\SalesExportCriteria;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SalesExport - Enterprise-Grade Export Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY-FIRST ARCHITECTURE                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  1. TENANT ISOLATION: Uses TenantId Value Object (not primitive int)    ║
 * ║  2. CSV INJECTION: SanitizesOutput trait escapes malicious formulas     ║
 * ║  3. AUDIT TRAIL: Factory dispatches SalesExportCreated for compliance   ║
 * ║  4. MEMORY SAFE: FromQuery + WithChunkReading for 100k+ records         ║
 * ║  5. CRITERIA PATTERN: SalesExportCriteria validates all filters         ║
 * ║  6. GDPR STRICT: Full PII redaction by default                          ║
 * ║  7. TIMEZONE SAFE: Uses app timezone for date calculations              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE (Controller):
 *   $export = SalesExport::create(
 *       tenantId: new TenantId($user->tenant_id),
 *       criteria: SalesExportCriteria::create($startDate, $endDate, $status),
 *       userId: $user->id,
 *       ipAddress: $request->ip(),
 *       userAgent: $request->userAgent(),
 *   );
 *   return Excel::download($export, 'sales.xlsx');
 */
class SalesExport implements FromQuery, ShouldQueue, WithChunkReading, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;
    use SanitizesOutput;

    // ═══════════════════════════════════════════════════════════════════════
    // Queue Resiliency Configuration
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Maximum retry attempts before marking job as failed.
     */
    public int $tries = 3;

    /**
     * Maximum seconds the job can run before being killed.
     * 10 minutes = 600 seconds (safe for 100k+ records with chunking).
     */
    public int $timeout = 600;

    /**
     * Maximum exceptions before job is marked as failed.
     */
    public int $maxExceptions = 2;

    private readonly string $exportId;

    /**
     * Private constructor - use factory method create() instead.
     *
     * ARCHITECTURE: Events should NOT be dispatched in constructors.
     * The factory pattern ensures audit events fire AFTER successful construction.
     */
    private function __construct(
        private readonly TenantId $tenantId,
        private readonly SalesExportCriteria $criteria,
        private readonly int $userId,
        private readonly bool $showFullPii = false,
        private readonly string $ipAddress = '0.0.0.0',
        private readonly string $userAgent = 'Unknown',
    ) {
        $this->exportId = (string) Str::uuid();
    }

    /**
     * Factory method - RECOMMENDED way to create exports.
     *
     * Dispatches audit event AFTER successful object construction,
     * preventing race conditions and orphaned audit records.
     *
     * @param  TenantId  $tenantId  The tenant context (MANDATORY)
     * @param  SalesExportCriteria  $criteria  The export filters (validated)
     * @param  int  $userId  The user requesting the export
     * @param  bool  $showFullPii  If true, show full phone/email (requires explicit permission)
     * @param  string  $ipAddress  Request IP for audit trail
     * @param  string  $userAgent  Request user agent for audit trail
     */
    public static function create(
        TenantId $tenantId,
        SalesExportCriteria $criteria,
        int $userId,
        bool $showFullPii = false,
        string $ipAddress = '0.0.0.0',
        string $userAgent = 'Unknown',
    ): self {
        // Step 1: Construct the export object
        $export = new self(
            tenantId: $tenantId,
            criteria: $criteria,
            userId: $userId,
            showFullPii: $showFullPii,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        // Step 2: Dispatch audit event AFTER successful construction
        $export->dispatchAuditEvent();

        return $export;
    }

    /**
     * Factory method for TESTING ONLY - skips audit event dispatch.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  WARNING: Testing Only - Do NOT use in production code                  ║
     * ╠══════════════════════════════════════════════════════════════════════════╣
     * ║  This method bypasses the audit trail, which is a compliance violation. ║
     * ║  It exists solely to avoid transaction conflicts in tests using         ║
     * ║  RefreshDatabase or DatabaseMigrations with Event::fake().              ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * @internal
     */
    public static function createForTesting(
        TenantId $tenantId,
        SalesExportCriteria $criteria,
        int $userId,
        bool $showFullPii = false,
        string $ipAddress = '0.0.0.0',
        string $userAgent = 'Unknown',
    ): self {
        // Construct WITHOUT dispatching audit event
        return new self(
            tenantId: $tenantId,
            criteria: $criteria,
            userId: $userId,
            showFullPii: $showFullPii,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Excel Sheet Configuration
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Professional sheet title with date range.
     */
    public function title(): string
    {
        $start = $this->criteria->startDate()->format('d-m-Y');
        $end = $this->criteria->endDate()->format('d-m-Y');

        return "Ventas {$start} a {$end}";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Query Builder (Tenant-Isolated)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Build the query with MANDATORY tenant filter.
     *
     * SECURITY: The tenant filter is applied FIRST, before any other conditions.
     * This ensures that even if other filters have bugs, tenant isolation is preserved.
     */
    public function query(): Builder
    {
        $query = Order::query()
            ->withoutGlobalScopes()
            // CRITICAL: Tenant filter FIRST - non-negotiable
            ->where('tenant_id', $this->tenantId->toString())
            // Eager load for N+1 prevention
            ->with(['items:id,order_id,quantity,product_name,total_price'])
            // Date range from Criteria (always present - DoS prevention)
            ->whereDate('created_at', '>=', $this->criteria->startDate()->format('Y-m-d'))
            ->whereDate('created_at', '<=', $this->criteria->endDate()->format('Y-m-d'))
            // Deterministic ordering for reproducible exports
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // Apply status filter if present
        if ($this->criteria->status()) {
            $query->where('status', $this->criteria->status());
        }

        return $query;
    }

    /**
     * Chunk size for memory-efficient processing.
     */
    public function chunkSize(): int
    {
        return config('exports.chunk_size', 500);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Data Mapping (Presentation Layer)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Column headings for the export file.
     */
    public function headings(): array
    {
        return [
            'ID Exportación',
            'Número de Orden',
            'Fecha',
            'Hora',
            'Cliente',
            'Teléfono',
            'Estado',
            'Método de Pago',
            'Productos',
            'Subtotal',
            'Descuento',
            'Envío',
            'Total',
        ];
    }

    /**
     * Map each Order to a row with SANITIZED output.
     *
     * SECURITY: All free-text fields are sanitized to prevent CSV Injection.
     * GDPR: PII is redacted by default unless explicit permission is granted.
     * ROBUSTNESS: Try/catch prevents single corrupt order from breaking export.
     */
    public function map($order): array
    {
        try {
            // Build products summary from items (null-safe)
            $products = $order->items?->map(function ($item) {
                return "{$item->quantity}x {$item->product_name}";
            })->join(', ') ?? 'Sin productos';

            $row = [
                $this->exportId,
                $order->order_number ?? 'N/A',
                $order->created_at?->format('Y-m-d') ?? 'N/A',
                $order->created_at?->format('H:i:s') ?? 'N/A',
                $this->redactName($order->customer_name),
                $this->redactPhone($order->customer_phone),
                $order->status_label ?? $order->status ?? 'Unknown',
                $order->payment_method ?? 'N/A',
                $products ?: 'Sin productos',
                $this->formatMoney($order->subtotal),
                $this->formatMoney($order->discount),
                $this->formatMoney($order->delivery_fee),
                $this->formatMoney($order->total),
            ];

            // Validate row count matches headings
            $expectedCount = count($this->headings());
            if (count($row) !== $expectedCount) {
                Log::warning('Export row count mismatch', [
                    'expected' => $expectedCount,
                    'actual' => count($row),
                    'order_id' => $order->id ?? 'unknown',
                ]);
                // Pad with empty strings if needed
                while (count($row) < $expectedCount) {
                    $row[] = '';
                }
            }

            return $this->sanitizeAll($row);

        } catch (\Throwable $e) {
            Log::error('Export map failed for order', [
                'order_id' => $order->id ?? 'unknown',
                'tenant_id' => $this->tenantId->toString(),
                'error' => $e->getMessage(),
            ]);

            // Return error row instead of throwing - don't break entire export
            return array_fill(0, count($this->headings()), 'ERROR');
        }
    }

    /**
     * Style the worksheet header row.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helper Methods (GDPR Compliance)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Format money values consistently.
     */
    private function formatMoney(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2);
    }

    /**
     * Redact customer name for GDPR compliance.
     *
     * If showFullPii is false (default), shows only first name initial + last name initial.
     */
    private function redactName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '[SIN NOMBRE]';
        }

        if ($this->showFullPii) {
            return $name;
        }

        // Redact: "Juan Carlos Pérez" → "J** C** P**"
        $parts = explode(' ', $name);
        $redacted = array_map(function ($part) {
            return strlen($part) > 0 ? $part[0].str_repeat('*', max(0, strlen($part) - 1)) : '';
        }, $parts);

        return implode(' ', $redacted);
    }

    /**
     * Redact phone number for GDPR compliance.
     *
     * FIXED: Uses strict null check instead of empty() to avoid false positives.
     *
     * STRICT MODE (default): Full redaction [REDACTED]
     * PERMISSIVE MODE (showFullPii=true): Show full phone
     */
    private function redactPhone(?string $phone): string
    {
        // FIXED: Strict null/empty check (not empty() which returns true for '0')
        if ($phone === null || $phone === '') {
            return '[SIN TELÉFONO]';
        }

        if ($this->showFullPii) {
            return $phone;
        }

        // GDPR STRICT: Full redaction, no partial exposure
        return '[REDACTED]';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Audit Trail (GDPR/SOC2 Compliance)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Dispatch audit event.
     *
     * ARCHITECTURE: Called by factory method AFTER successful construction,
     * not in constructor, to prevent race conditions and orphaned records.
     */
    private function dispatchAuditEvent(): void
    {
        try {
            event(SalesExportCreated::create(
                exportId: $this->exportId,
                tenantId: \App\ValueObjects\TenantId::fromString($this->tenantId->toString()),
                userId: $this->userId,
                format: 'xlsx',
                criteriaHash: $this->criteria->toHash(),
                criteriaDetails: $this->criteria->toArray(),
                ipAddress: $this->ipAddress,
                userAgent: $this->userAgent,
            ));

            Log::info('Sales export created', [
                'export_id' => $this->exportId,
                'tenant_id' => $this->tenantId->toString(),
                'user_id' => $this->userId,
                'criteria' => $this->criteria->toArray(),
            ]);

        } catch (\Throwable $e) {
            // CRITICAL: Log with full details for forensics
            Log::critical('Sales export audit failed - POTENTIAL COMPLIANCE ISSUE', [
                'export_id' => $this->exportId,
                'tenant_id' => $this->tenantId->toString(),
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            // Re-throw to prevent export without audit trail
            throw new \RuntimeException(
                'Cannot create export without audit trail. Please try again.',
                0,
                $e
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Accessors for Testing
    // ═══════════════════════════════════════════════════════════════════════

    public function getExportId(): string
    {
        return $this->exportId;
    }

    public function getTenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function getCriteria(): SalesExportCriteria
    {
        return $this->criteria;
    }

    public function isShowingFullPii(): bool
    {
        return $this->showFullPii;
    }
}
