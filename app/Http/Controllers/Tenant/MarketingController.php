<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class MarketingController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->to(route('filament.app.pages.manage-marketing', [
            'tenant' => $this->tenantSlug(),
        ], false));
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
