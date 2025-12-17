<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Interaction;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * InteractionController - Analytics Tracking
 * 
 * Métodos:
 * - store(): API endpoint para tracking AJAX
 * - whatsapp(): Redirect middleware para WhatsApp
 * - call(): Redirect middleware para llamadas
 * - maps(): Redirect middleware para navegación GPS
 */
class InteractionController extends Controller
{
    /**
     * API Endpoint para tracking AJAX (Existente)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'type' => 'required|in:view,whatsapp_click',
            'product_id' => 'nullable|exists:products,id',
        ]);

        // Duplicate Check (5 minutes, same IP, same product)
        $ip = request()->ip();
        $recentInteraction = Interaction::where('tenant_id', $validated['tenant_id'])
            ->where('product_id', $validated['product_id'])
            ->where('type', $validated['type'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($recentInteraction) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        Interaction::create($validated);

        // Automatic Lead Creation on WhatsApp Click
        if ($validated['type'] === 'whatsapp_click') {
            $lead = \App\Models\Lead::create([
                'tenant_id' => $validated['tenant_id'],
                'product_id' => $validated['product_id'],
                'message' => 'Clic en WhatsApp desde el Catálogo',
                'status' => 'new',
            ]);

            // Notify Tenant Owner
            $tenant = Tenant::find($validated['tenant_id']);
            if ($tenant && $tenant->user) {
                \Filament\Notifications\Notification::make()
                    ->title('Nuevo Cliente Interesado')
                    ->body('Alguien hizo clic en el producto: '.($lead->product->name ?? 'Producto'))
                    ->success()
                    ->sendToDatabase($tenant->user);
            }
        }

        return response()->json(['status' => 'success']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REDIRECT TRACKING METHODS (Para el Dashboard Bento Grid)
    // Estos métodos registran la interacción y redirigen al destino externo
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Registra clic en WhatsApp y redirige
     */
    public function whatsapp(Request $request, Tenant $tenant): RedirectResponse
    {
        // Construir URL de WhatsApp
        $phone = preg_replace('/[^0-9]/', '', $tenant->whatsapp_number ?? $tenant->phone ?? '');
        
        if (empty($phone)) {
            return redirect()->back()->with('error', 'Este negocio no tiene WhatsApp configurado.');
        }
        
        // Agregar código de país Honduras si no lo tiene
        if (strlen($phone) === 8) {
            $phone = '504' . $phone;
        }

        // Mensaje opcional (puede venir de producto específico)
        $text = $request->input('text', '');
        
        $finalUrl = "https://wa.me/{$phone}";
        if (!empty($text)) {
            $finalUrl .= "?text=" . urlencode($text);
        }

        // Registrar interacción
        Interaction::create([
            'tenant_id' => $tenant->id,
            'type' => Interaction::TYPE_WHATSAPP_CLICK,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'url' => $request->fullUrl(),
            'metadata' => !empty($text) ? ['message' => substr($text, 0, 200)] : null,
        ]);

        return redirect()->away($finalUrl);
    }

    /**
     * Registra clic en llamada telefónica y redirige
     */
    public function call(Request $request, Tenant $tenant): RedirectResponse
    {
        $phone = $tenant->phone ?? $tenant->whatsapp_number ?? '';
        
        if (empty($phone)) {
            return redirect()->back()->with('error', 'Este negocio no tiene teléfono configurado.');
        }

        Interaction::create([
            'tenant_id' => $tenant->id,
            'type' => Interaction::TYPE_CALL_CLICK,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]);

        return redirect()->away("tel:{$phone}");
    }

    /**
     * Registra clic en navegación GPS y redirige
     */
    public function maps(Request $request, Tenant $tenant): RedirectResponse
    {
        // Priorizar coordenadas GPS
        if (!empty($tenant->latitude) && !empty($tenant->longitude)) {
            $url = "https://www.google.com/maps/dir/?api=1&destination={$tenant->latitude},{$tenant->longitude}";
        } elseif (!empty($tenant->address)) {
            $url = "https://www.google.com/maps/search/?api=1&query=" . urlencode($tenant->address);
        } else {
            return redirect()->back()->with('error', 'Este negocio no tiene ubicación configurada.');
        }

        Interaction::create([
            'tenant_id' => $tenant->id,
            'type' => 'maps_click',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]);

        return redirect()->away($url);
    }
}

