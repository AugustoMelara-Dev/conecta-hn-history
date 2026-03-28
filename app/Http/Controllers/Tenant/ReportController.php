<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Reporting\Commands\QueuePdfReportCommand;
use App\Application\Reporting\Handlers\QueuePdfReportHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\InventoryReportRequest;
use App\Http\Requests\Api\V1\Reports\SalesReportRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class ReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->to(route('filament.app.pages.growth-center', [
            'tenant' => $this->tenantSlug(),
        ], false));
    }

    public function salesPdf(
        SalesReportRequest $request,
        QueuePdfReportHandler $handler
    ): RedirectResponse {
        $requestor = $request->user();

        if (! $requestor instanceof User) {
            abort(403);
        }

        $handler->handle(new QueuePdfReportCommand(
            tenantId: $this->tenantContext->getTenantUuid(),
            requestorId: $requestor->getAuthIdentifier(),
            reportType: 'sales',
            startDate: $request->getStartDate(),
            endDate: $request->getEndDate(),
        ));

        return redirect()
            ->to(route('filament.app.pages.growth-center', ['tenant' => $this->tenantSlug()], false))
            ->with('success', 'Tu reporte de ventas se esta generando en segundo plano y aparecera por notificacion cuando este listo.');
    }

    public function inventoryPdf(
        InventoryReportRequest $request,
        QueuePdfReportHandler $handler
    ): RedirectResponse {
        $requestor = $request->user();

        if (! $requestor instanceof User) {
            abort(403);
        }

        $handler->handle(new QueuePdfReportCommand(
            tenantId: $this->tenantContext->getTenantUuid(),
            requestorId: $requestor->getAuthIdentifier(),
            reportType: 'inventory',
        ));

        return redirect()
            ->to(route('filament.app.pages.growth-center', ['tenant' => $this->tenantSlug()], false))
            ->with('success', 'Tu reporte de inventario se esta generando en segundo plano y aparecera por notificacion cuando este listo.');
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
