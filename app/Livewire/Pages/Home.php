<?php

namespace App\Livewire\Pages;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Livewire\Component;

/**
 * Home - Página Principal SSR
 * 
 * Componente Livewire para renderizado del lado del servidor
 * maximizando SEO y velocidad de carga inicial.
 * 
 * Secciones:
 * 1. Hero de Búsqueda Inteligente (Typewriter Effect)
 * 2. Navegación Táctil de Categorías (Pills Horizontales)
 * 3. Ciudades Destacadas (SEO y Aterrizaje)
 * 4. Nuevos en Conecta HN (Prueba Social)
 */
class Home extends Component
{
    /**
     * Término de búsqueda del usuario
     */
    public string $search = '';

    /**
     * Renderiza la vista con datos optimizados
     */
    public function render()
    {
        return view('livewire.pages.home', [
            // ═══════════════════════════════════════════════════════════════
            // CATEGORÍAS: Ordenadas por sort_order para navegación táctil
            // ═══════════════════════════════════════════════════════════════
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->select(['id', 'name', 'slug', 'icon', 'color'])
                ->take(12)
                ->get(),

            // ═══════════════════════════════════════════════════════════════
            // CIUDADES: Activas con hero_image para landing pages SEO
            // ═══════════════════════════════════════════════════════════════
            'cities' => City::query()
                ->where('is_available', true)
                ->select(['id', 'name', 'slug', 'hero_image', 'department'])
                ->withCount('tenants')
                ->orderByDesc('tenants_count')
                ->take(6)
                ->get(),

            // ═══════════════════════════════════════════════════════════════
            // NEGOCIOS NUEVOS: Prueba social de vitalidad
            // ═══════════════════════════════════════════════════════════════
            'newTenants' => Tenant::query()
                ->where('is_active', true)
                ->where('status', 'approved')
                ->with(['category:id,name,color'])
                ->select(['id', 'name', 'slug', 'logo_path', 'category_id', 'created_at'])
                ->latest()
                ->take(8)
                ->get(),

            // ═══════════════════════════════════════════════════════════════
            // HERO IMAGE: Imagen aleatoria de ciudades para el fondo
            // ═══════════════════════════════════════════════════════════════
            'heroImage' => City::query()
                ->whereNotNull('hero_image')
                ->inRandomOrder()
                ->value('hero_image'),

            // ═══════════════════════════════════════════════════════════════
            // ESTADÍSTICAS: Para prueba social
            // ═══════════════════════════════════════════════════════════════
            'stats' => [
                'tenants' => Tenant::where('is_active', true)->count(),
                'cities' => City::where('is_available', true)->count(),
                'categories' => Category::where('is_active', true)->count(),
            ],
        ])->layout('layouts.public', [
            'title' => 'Conecta HN - El Directorio de Negocios de Honduras',
            'description' => 'Descubre los mejores restaurantes, tiendas y servicios en Honduras. Tu guía local para encontrar negocios cerca de ti.',
        ]);
    }

    /**
     * Búsqueda en tiempo real (wire:model.live.debounce)
     */
    public function updatedSearch()
    {
        if (strlen($this->search) >= 3) {
            $this->dispatch('search-updated', search: $this->search);
        }
    }

    /**
     * Navegar a resultados de búsqueda
     */
    public function performSearch()
    {
        if (!empty($this->search)) {
            return redirect()->route('search', ['q' => $this->search]);
        }
    }
}
