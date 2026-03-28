<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): Response
    {
        $tenantData = $this->tenantContext->getTenantData(['simple_schedule']);

        return Inertia::render('Tenant/Settings/Index', [
            'schedule' => $tenantData['simple_schedule'] ?? null,
            'dayLabels' => [
                'monday' => 'Lunes',
                'tuesday' => 'Martes',
                'wednesday' => 'Miercoles',
                'thursday' => 'Jueves',
                'friday' => 'Viernes',
                'saturday' => 'Sabado',
                'sunday' => 'Domingo',
            ],
        ]);
    }

    public function updateProfile(
        Request $request
    ): RedirectResponse {
        return $this->redirectToOfficialSettings('La configuracion del negocio ya se administra desde el panel oficial en /app.');
    }

    public function updateSchedule(
        Request $request
    ): RedirectResponse {
        return $this->redirectToOfficialSettings('La configuracion del negocio ya se administra desde el panel oficial en /app.');
    }

    private function redirectToOfficialSettings(string $message): RedirectResponse
    {
        return redirect()
            ->to(route('filament.app.pages.settings', ['tenant' => $this->tenantSlug()], false))
            ->with('error', $message);
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
