<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Traits\GeneratesSignedDownloadUrls;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GeneratePdfReportJob - Polymorphic Async PDF Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FIRE-AND-FORGET: Heavy DOM parsing happens in background worker        ║
 * ║  POLYMORPHIC: Single job handles Sales, Inventory, and Kitchen PDFs     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SUPPORTED REPORT TYPES:
 * - 'sales': Sales summary with order breakdown
 * - 'inventory': Stock manifest for physical inventory check
 * - 'kitchen': Prep list for pending/processing orders
 *
 * RESOURCE ALLOCATION:
 * Queue workers have separate memory/time limits from FPM.
 * We can safely render complex PDFs without timeout risk.
 */
class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GeneratesSignedDownloadUrls;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $reportType,
        public readonly array $filters,
        public readonly User $requestor
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('GeneratePdfReportJob: Starting PDF generation', [
            'tenant_id' => $this->tenant->id,
            'report_type' => $this->reportType,
            'requestor_id' => $this->requestor->id,
            'filters' => $this->filters,
        ]);

        try {
            // ═══════════════════════════════════════════════════════════════
            // STEP 1: Generate unique filename with tenant isolation
            // ═══════════════════════════════════════════════════════════════
            $uuid = (string) Str::uuid();
            $filename = sprintf(
                'reports/%s/%s_%s.pdf',
                $this->tenant->slug ?? $this->tenant->id,
                $this->reportType,
                $uuid
            );

            // ═══════════════════════════════════════════════════════════════
            // STEP 2: Polymorphic PDF generation based on report type
            // ═══════════════════════════════════════════════════════════════
            $pdf = match ($this->reportType) {
                'sales' => $this->generateSalesPdf(),
                'inventory' => $this->generateInventoryPdf(),
                'kitchen' => $this->generateKitchenPdf(),
                default => throw new \InvalidArgumentException("Unknown report type: {$this->reportType}"),
            };

            // ═══════════════════════════════════════════════════════════════
            // STEP 3: Save to disk (not stream)
            // ═══════════════════════════════════════════════════════════════
            Storage::disk('local')->put($filename, $pdf->output());

            Log::info('GeneratePdfReportJob: File stored successfully', [
                'filename' => $filename,
                'tenant_id' => $this->tenant->id,
                'report_type' => $this->reportType,
            ]);

            // ═══════════════════════════════════════════════════════════════
            // STEP 4: Generate signed download URL (valid for 24 hours)
            // ═══════════════════════════════════════════════════════════════
            // NOTE: Storage::temporaryUrl() only works with S3/Azure.
            // For local disk, we use Laravel's signed routes instead.
            $expiresAt = now()->addHours(24);
            $downloadUrl = $this->generateDownloadUrl($filename, $expiresAt);

            // ═══════════════════════════════════════════════════════════════
            // STEP 5: Notify requestor via database/email
            // ═══════════════════════════════════════════════════════════════
            $this->requestor->notify(new ReportReadyNotification(
                reportType: $this->reportType,
                downloadUrl: $downloadUrl,
                tenantName: $this->tenant->name,
                expiresAt: $expiresAt,
            ));

            Log::info('GeneratePdfReportJob: Notification sent', [
                'requestor_id' => $this->requestor->id,
            ]);

        } catch (\Illuminate\View\ViewException $e) {
            // Handle view rendering errors gracefully
            Log::error('GeneratePdfReportJob: View rendering failed', [
                'tenant_id' => $this->tenant->id,
                'report_type' => $this->reportType,
                'error' => $e->getMessage(),
            ]);
            // Don't retry - view errors are typically not transient
            $this->fail($e);

        } catch (\Exception $e) {
            Log::error('GeneratePdfReportJob: Failed to generate PDF', [
                'tenant_id' => $this->tenant->id,
                'report_type' => $this->reportType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Generate Sales Summary PDF.
     */
    protected function generateSalesPdf(): \Barryvdh\DomPDF\PDF
    {
        $query = Order::where('tenant_id', $this->tenant->id)
            ->with(['items.product:id,name'])
            ->orderBy('created_at', 'desc');

        if (! empty($this->filters['start_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }

        if (! empty($this->filters['end_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // Limit for PDF (prevent huge documents)
        $orders = $query->limit(500)->get();

        // Calculate summary
        $summary = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total'),
            'avg_order_value' => $orders->avg('total') ?? 0,
            'by_status' => $orders->groupBy('status')->map->count(),
            'by_payment' => $orders->groupBy(fn ($o) => $o->payment_method?->value ?? 'unknown')->map->count(),
        ];

        return Pdf::loadView('reports.sales-summary', [
            'tenant' => $this->tenant,
            'orders' => $orders,
            'summary' => $summary,
            'start_date' => $this->filters['start_date'] ?? null,
            'end_date' => $this->filters['end_date'] ?? null,
            'generated_at' => now(),
        ]);
    }

    /**
     * Generate Inventory Manifest PDF.
     */
    protected function generateInventoryPdf(): \Barryvdh\DomPDF\PDF
    {
        $products = $this->tenant->products()
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);

        $pdf = Pdf::loadView('reports.inventory-manifest', [
            'tenant' => $this->tenant,
            'products' => $products,
            'low_stock_count' => $products->filter(fn ($p) => $p->stock_quantity <= $p->low_stock_threshold)->count(),
            'out_of_stock_count' => $products->filter(fn ($p) => $p->stock_quantity <= 0)->count(),
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * Generate Kitchen Prep List PDF.
     */
    protected function generateKitchenPdf(): \Barryvdh\DomPDF\PDF
    {
        $orders = Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING])
            ->with(['items.product:id,name'])
            ->orderBy('created_at', 'asc')
            ->get();

        $pdf = Pdf::loadView('reports.kitchen-prep', [
            'tenant' => $this->tenant,
            'orders' => $orders,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GeneratePdfReportJob: Job failed permanently', [
            'tenant_id' => $this->tenant->id,
            'report_type' => $this->reportType,
            'requestor_id' => $this->requestor->id,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Notify requestor about failure via separate notification
    }
}
