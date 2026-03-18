<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Psr\Log\LoggerInterface;

/**
 * RegisteredUserController - Thin HTTP Gateway (Clean Controller)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero inline validation (delegated to RegisterTenantRequest)
 * - Zero business logic (delegated to Command Handlers)
 * - Single Responsibility: HTTP translation only
 */
final class RegisteredUserController extends Controller
{
    public function __construct(
        private StatefulGuard $auth,
        private LoggerInterface $logger,
        private CommandBusInterface $bus
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * FLOW:
     * 1. RegisterTenantRequest validates input (automatic via DI)
     * 2. RegisterTenantCommand created from validated data
     * 3. Command dispatched via Bus
     * 4. Controller handles response/redirect
     */
    public function store(RegisterTenantRequest $request): RedirectResponse
    {
        try {
            $cityId = (int) ($request->validated('city_id') ?? \App\Models\City::value('id') ?? 1);
            $categoryId = (int) ($request->validated('category_id') ?? \App\Models\Category::value('id') ?? 1);

            $command = new RegisterTenantCommand(
                businessName: $request->validated('business_name'),
                slug: Str::slug($request->validated('slug')),
                ownerName: $request->validated('name'),
                email: $request->validated('email'),
                password: $request->validated('password'),
                cityId: $cityId,
                categoryId: $categoryId,
                whatsapp: $request->validated('whatsapp'),
                selectedPlan: $request->validated('selected_plan', 'free'),
                idempotencyKey: $request->header('X-Idempotency-Key')
            );

            /** @var \App\Application\Tenant\Results\RegisterTenantResult $result */
            $result = $this->bus->dispatch($command);

            // Authentication
            $user = \App\Models\User::where('email', $result->ownerEmail)->firstOrFail();
            $this->auth->login($user);

            return redirect()->route('filament.admin.pages.dashboard')->with('success', '¡Bienvenido a Conecta HN! Tu negocio está activo.');

        } catch (\Exception $e) {
            // Graceful Degradation: Log and return user-friendly error
            $this->logger->error('User registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->validated('email'),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Hubo un problema inesperado al crear tu cuenta: '.$e->getMessage()]);
        }
    }
}
