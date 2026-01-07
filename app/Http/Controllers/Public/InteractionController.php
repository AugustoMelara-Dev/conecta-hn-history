<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Actions\Interactions\RecordInteractionAction;
use App\Application\DTOs\Interactions\RecordInteractionData;
use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * InteractionController - Public Redirect Tracking
 * 
 * Handles Click-to-Action redirects (WhatsApp, Calls, Maps).
 */
class InteractionController extends Controller
{
    public function whatsapp(Request $request, Tenant $tenant, RecordInteractionAction $action): RedirectResponse
    {
        $phone = preg_replace('/[^0-9]/', '', $tenant->whatsapp_number ?? $tenant->phone ?? '');

        if (empty($phone)) {
            return redirect()->back()->with('error', 'Este negocio no tiene WhatsApp configurado.');
        }

        if (strlen($phone) === 8) {
            $phone = '504' . $phone;
        }

        $text = $request->input('text', '');
        $finalUrl = "https://wa.me/{$phone}";
        if (!empty($text)) {
            $finalUrl .= '?text=' . urlencode($text);
        }

        $dto = RecordInteractionData::forRedirect(
            tenantId: $tenant->id,
            type: Interaction::TYPE_WHATSAPP_CLICK,
            request: $request,
            metadata: !empty($text) ? ['message' => substr($text, 0, 200)] : null
        );
        $action->execute($dto);

        return redirect()->away($finalUrl);
    }

    public function call(Request $request, Tenant $tenant, RecordInteractionAction $action): RedirectResponse
    {
        $phone = $tenant->phone ?? $tenant->whatsapp_number ?? '';

        if (empty($phone)) {
            return redirect()->back()->with('error', 'Este negocio no tiene teléfono configurado.');
        }

        $dto = RecordInteractionData::forRedirect(
            tenantId: $tenant->id,
            type: Interaction::TYPE_CALL_CLICK,
            request: $request
        );
        $action->execute($dto);

        return redirect()->away("tel:{$phone}");
    }

    public function maps(Request $request, Tenant $tenant, RecordInteractionAction $action): RedirectResponse
    {
        if (!empty($tenant->latitude) && !empty($tenant->longitude)) {
            $url = "https://www.google.com/maps/dir/?api=1&destination={$tenant->latitude},{$tenant->longitude}";
        } elseif (!empty($tenant->address)) {
            $url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($tenant->address);
        } else {
            return redirect()->back()->with('error', 'Este negocio no tiene ubicación configurada.');
        }

        $dto = RecordInteractionData::forRedirect(
            tenantId: $tenant->id,
            type: 'maps_click',
            request: $request
        );
        $action->execute($dto);

        return redirect()->away($url);
    }
}
