<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreWaitlistRequest;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

final class WaitlistController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository
    ) {}

    public function store(StoreWaitlistRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $validated = $request->validated();
        $date = Carbon::parse($validated['date'])->toDateString();

        $exists = Waitlist::where('tenant_id', $tenant->id())
            ->where('service_id', $validated['service_id'])
            ->where('customer_email', $validated['customer_email'])
            ->where('requested_date', $date)
            ->active()
            ->exists();

        if (! $exists) {
            Waitlist::create([
                'tenant_id' => $tenant->id(),
                'service_id' => $validated['service_id'],
                'user_id' => auth()->id(),
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'requested_date' => $date,
                'status' => 'active',
            ]);
        }

        return back()->with('success', 'Te hemos añadido a la lista de espera. Te notificaremos si se libera un espacio.');
    }
}

