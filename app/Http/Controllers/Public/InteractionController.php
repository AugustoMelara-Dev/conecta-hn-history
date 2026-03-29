<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Interactions\DTOs\InteractionData;
use App\Domain\Analytics\Enums\InteractionType;
use App\Domain\Shared\ValueObjects\PhoneNumber;
use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use App\Infrastructure\Interactions\Actions\RecordInteractionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Registra interacciones públicas y redirige al canal solicitado.
 */
final class InteractionController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly RecordInteractionAction $recordAction
    ) {}

    public function whatsapp(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantResolver->findPublicBySlug($tenantSlug);

        if (! $tenant) {
            abort(404, 'Negocio no encontrado.');
        }

        $phone = new PhoneNumber($tenant->whatsappNumber() ?? $tenant->phone());

        if (! $phone->isValid()) {
            return redirect()->back()->with('error', 'Este negocio no tiene WhatsApp configurado.');
        }

        $text = $request->input('text', '');

        $dto = new InteractionData(
            tenantId: $tenant->id(),
            type: InteractionType::WHATSAPP_CLICK->value,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $request->session()->getId(),
            metadata: ! empty($text) ? ['message' => substr($text, 0, 200)] : null,
        );
        $this->recordAction->execute($dto);

        return redirect()->away($phone->toWhatsappLink($text));
    }

    public function call(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantResolver->findPublicBySlug($tenantSlug);

        if (! $tenant) {
            abort(404, 'Negocio no encontrado.');
        }

        $phone = new PhoneNumber($tenant->phone() ?? $tenant->whatsappNumber());

        if (! $phone->isValid()) {
            return redirect()->back()->with('error', 'Este negocio no tiene teléfono configurado.');
        }

        $dto = new InteractionData(
            tenantId: $tenant->id(),
            type: InteractionType::CALL->value,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $request->session()->getId(),
        );
        $this->recordAction->execute($dto);

        return redirect()->away($phone->toCallLink());
    }

    public function maps(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantResolver->findPublicBySlug($tenantSlug);

        if (! $tenant) {
            abort(404, 'Negocio no encontrado.');
        }

        if (! empty($tenant->latitude()) && ! empty($tenant->longitude())) {
            $url = sprintf(
                'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
                $tenant->latitude(),
                $tenant->longitude()
            );
        } elseif (! empty($tenant->address())) {
            $url = 'https://www.google.com/maps/search/?api=1&query='.urlencode($tenant->address());
        } else {
            return redirect()->back()->with('error', 'Este negocio no tiene ubicación configurada.');
        }

        $dto = new InteractionData(
            tenantId: $tenant->id(),
            type: InteractionType::LOCATION_VIEW->value,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $request->session()->getId(),
        );
        $this->recordAction->execute($dto);

        return redirect()->away($url);
    }
}
