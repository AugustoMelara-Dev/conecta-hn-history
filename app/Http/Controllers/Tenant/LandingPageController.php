<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LandingPageController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->to(route('filament.app.tenant.profile', [
            'tenant' => $this->tenantSlug(),
        ], false));
    }

    public function update(Request $request): RedirectResponse
    {
        return redirect()
            ->to(route('filament.app.tenant.profile', ['tenant' => $this->tenantSlug()], false))
            ->with('error', 'La presencia publica ya se administra desde el editor oficial del micrositio en /app.');
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
