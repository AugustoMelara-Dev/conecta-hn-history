<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LegacyWorkspaceActionController extends Controller
{
    public function __construct(
        private readonly CanonicalPanelUrlResolver $panelUrlResolver
    ) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse|Response
    {
        $message = 'Esta accion del workspace legacy fue retirada. Usa el panel operativo canonico en /app/{tenant}.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 410);
        }

        if ($request->isMethod('GET')) {
            return redirect()->to(
                $this->panelUrlResolver->forUser($request->user(), $this->sectionForRoute((string) $request->route()?->getName()))
            );
        }

        return response($message, 410);
    }

    private function sectionForRoute(string $routeName): string
    {
        return match ($routeName) {
            'orders.print-ticket', 'receipt.download' => 'orders',
            'reports.sales-pdf', 'reports.inventory-pdf' => 'reports',
            default => 'dashboard',
        };
    }
}
