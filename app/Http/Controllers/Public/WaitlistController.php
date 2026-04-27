<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Shared\Services\TenantContext;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreWaitlistRequest;
use App\Models\Tenant as TenantModel;
use App\Models\Waitlist;
use App\Support\Diagnostics\SupportDiagnosticFactory;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

final class WaitlistController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly SupportDiagnosticFactory $diagnostics,
        private readonly TenantVisibilityService $visibility
    ) {}

    public function store(StoreWaitlistRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $tenantModel = TenantModel::query()
            ->withoutGlobalScopes()
            ->where('slug', $tenantSlug)
            ->first();

        if ($tenantModel === null) {
            abort(404, 'Tenant not found.');
        }

        $this->visibility->check($tenantModel);

        app(TenantContext::class)->setTenant((string) $tenant->id());

        $validated = $request->validated();
        $date = Carbon::parse($validated['date'])->toDateString();
        $normalizedEmail = strtolower(trim((string) $validated['customer_email']));

        $exists = Waitlist::where('tenant_id', $tenant->id())
            ->where('service_id', $validated['service_id'])
            ->where('normalized_email', $normalizedEmail)
            ->where('requested_date', $date)
            ->active()
            ->exists();

        if (! $exists) {
            try {
                Waitlist::create([
                    'tenant_id' => $tenant->id(),
                    'service_id' => $validated['service_id'],
                    'user_id' => auth()->id(),
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_email' => $validated['customer_email'],
                    'normalized_email' => $normalizedEmail,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'requested_date' => $date,
                    'status' => 'active',
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $exists = true;
            }
        }

        return back()
            ->with('success', $exists
                ? 'Ya estabas en la lista de espera para esa fecha. Te avisaremos si se libera un espacio.'
                : 'Te hemos anadido a la lista de espera. Te notificaremos si se libera un espacio.')
            ->with('diagnostic', $this->diagnostics->make(
                'waitlist',
                $exists ? 'Registro existente' : 'Lista de espera activa',
                $exists ? 'Ya existia una solicitud activa con ese correo, servicio y fecha.' : 'La solicitud quedo registrada para seguimiento del negocio.',
                'Si necesitas soporte, comparte la referencia visible y el negocio.'
            ));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            || (string) ($exception->errorInfo[0] ?? '') === '23000'
            || ($exception->errorInfo[1] ?? null) === 1062;
    }
}
