<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'type' => 'required|in:view,whatsapp_click',
            'product_id' => 'nullable|exists:products,id',
        ]);

        // Duplicate Check (5 minutes, same IP, same product)
        $ip = request()->ip();
        $recentInteraction = \App\Models\Interaction::where('tenant_id', $validated['tenant_id'])
            ->where('product_id', $validated['product_id'])
            ->where('type', $validated['type'])
            ->where('created_at', '>=', now()->subMinutes(5))
            // In a real app, we would store IP. For now, we rely on session or just time.
            ->first();

        if ($recentInteraction) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        \App\Models\Interaction::create($validated);

        // Automatic Lead Creation on WhatsApp Click
        if ($validated['type'] === 'whatsapp_click') {
            $lead = \App\Models\Lead::create([
                'tenant_id' => $validated['tenant_id'],
                'product_id' => $validated['product_id'],
                'message' => 'Clic en WhatsApp desde el Catálogo',
                'status' => 'new',
            ]);

            // Notify Tenant Owner
            $tenant = \App\Models\Tenant::find($validated['tenant_id']);
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
}
