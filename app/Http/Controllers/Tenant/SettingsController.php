<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Tenant\Commands\UpdateTenantBrandingCommand;
use App\Application\Tenant\Commands\UpdateTenantScheduleCommand;
use App\Application\Tenant\Handlers\UpdateTenantBrandingHandler;
use App\Application\Tenant\Handlers\UpdateTenantScheduleHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Shared\DTOs\FileTransferObject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateScheduleRequest;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use Illuminate\Http\RedirectResponse;
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
        UpdateTenantProfileRequest $request,
        UpdateTenantBrandingHandler $handler
    ): RedirectResponse {
        $validated = $request->validated();

        $handler->handle(new UpdateTenantBrandingCommand(
            tenantId: $this->tenantContext->getTenantUuid(),
            logo: $request->file('logo') ? FileTransferObject::fromUploadedFile($request->file('logo')) : null,
            banner: $request->file('cover') ? FileTransferObject::fromUploadedFile($request->file('cover')) : null,
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            about: isset($validated['about']) ? (string) $validated['about'] : null,
            phone: isset($validated['phone']) ? (string) $validated['phone'] : null,
            whatsapp: isset($validated['whatsapp_number']) ? (string) $validated['whatsapp_number'] : null,
            address: isset($validated['address']) ? (string) $validated['address'] : null,
            website: isset($validated['website_url']) ? (string) $validated['website_url'] : null,
            instagram: isset($validated['instagram_url']) ? (string) $validated['instagram_url'] : null,
            facebook: isset($validated['facebook_url']) ? (string) $validated['facebook_url'] : null,
            tiktok: isset($validated['tiktok_url']) ? (string) $validated['tiktok_url'] : null,
            primaryColor: isset($validated['primary_color']) ? (string) $validated['primary_color'] : null,
        ));

        return back()->with('success', 'Perfil actualizado.');
    }

    public function updateSchedule(
        UpdateScheduleRequest $request,
        UpdateTenantScheduleHandler $handler
    ): RedirectResponse {
        $validated = $request->validated();

        $handler->handle(new UpdateTenantScheduleCommand(
            tenantId: $this->tenantContext->getTenantUuid(),
            scheduleData: is_array($validated['schedule'] ?? null) ? $validated['schedule'] : [],
        ));

        return back()->with('success', 'Horarios actualizados.');
    }
}
