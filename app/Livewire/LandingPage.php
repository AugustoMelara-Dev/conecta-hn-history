<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * LandingPage - Página de Ventas B2B para Conecta HN
 *
 * Diseñada para convencer a dueños de negocios de registrarse.
 * Transmite: confianza, modernidad, "cero fricción".
 *
 * NO es una página para buscar comida, es una página de ventas SaaS.
 */
class LandingPage extends Component
{
    /**
     * Renderiza la vista con layout limpio (sin sidebar)
     */
    public function render()
    {
        return view('livewire.landing-page')
            ->layout('components.layouts.landing', [
                'title' => 'Conecta HN - Tu Micrositio Profesional en 5 Minutos',
                'description' => 'Crea tu catálogo digital gratis. Vende más rápido con pedidos directos por WhatsApp.',
            ]);
    }
}

