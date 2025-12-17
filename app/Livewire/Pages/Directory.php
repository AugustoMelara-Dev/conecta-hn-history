<?php

namespace App\Livewire\Pages;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Directory - El Núcleo Transaccional del Marketplace
 *
 * Single Page Application con sincronización de URL y estado.
 * Diseñado para conversión inmediata con cero fricción.
 *
 * Características:
 * - Deep Linking: Estado vive en la URL (/directorio?isOpen=true)
 * - Monetización: PRO primero, luego random para Free
 * - Open/Closed: Cálculo dinámico vía Carbon
 */
class Directory extends Component
{
    use WithPagination;

    // ═══════════════════════════════════════════════════════════════════════
    // PROPIEDADES CON SINCRONIZACIÓN DE URL (Deep Linking Avanzado)
    // history: true = Registra en historial del navegador (back button)
    // keep: true = Persiste incluso con valor vacío
    // ═══════════════════════════════════════════════════════════════════════

    #[Url(history: true, keep: true, except: '')]
    public string $search = '';

    #[Url(history: true, keep: true, except: '')]
    public string $category = '';

    #[Url(history: true, keep: true, except: '')]
    public string $city = '';

    #[Url(history: true, keep: true, except: false)]
    public bool $isOpen = false;

    #[Url(history: true, keep: true, except: 'relevance')]
    public string $sort = 'relevance';

    /**
     * Cantidad de resultados por carga
     */
    public int $perPage = 12;

    /**
     * Control del drawer móvil (Bottom Sheet)
     */
    public bool $showFiltersDrawer = false;

    /**
     * Hora actual para cálculo de horarios
     */
    public string $currentTime;

    /**
     * Día actual de la semana
     */
    public int $currentDay;

    /**
     * Inicialización con parámetros GET
     * Handshake con la Home
     */
    public function mount(): void
    {
        // Los parámetros GET se capturan automáticamente por #[Url]
        // Si el usuario buscó "Pizza" en home, el filtro ya está activo
        // Nota: currentTime se actualiza en render() para evitar stale data
    }

    /**
     * Renderiza la vista con datos filtrados
     *
     * IMPORTANTE: Actualizamos currentTime aquí (no en mount)
     * para que cada loadMore() tenga el tiempo real actualizado.
     * Esto evita el edge case de usuarios que permanecen en la página
     * y ven negocios "Abiertos" que ya cerraron.
     */
    public function render()
    {
        // Actualizar tiempo en cada renderizado (incluyendo loadMore)
        $this->currentTime = now()->format('H:i');
        $this->currentDay = now()->dayOfWeek;

        return view('livewire.pages.directory', [
            'tenants' => $this->getFilteredTenants(),
            'categories' => $this->getCategories(),
            'cities' => $this->getCities(),
            'activeFiltersCount' => $this->getActiveFiltersCount(),
            'searchTerm' => $this->search,
            'selectedCityName' => $this->getSelectedCityName(),
        ])->layout('layouts.public', [
            'title' => $this->getPageTitle(),
            'description' => $this->getPageDescription(),
        ]);
    }

    /**
     * Consulta principal con algoritmo de monetización
     * PRO primero (random entre ellos), luego Free (random)
     */
    protected function getFilteredTenants()
    {
        $query = Tenant::query()
            ->where('is_active', true)
            ->where('status', 'approved');

        // ═══════════════════════════════════════════════════════════════
        // FILTROS CONDICIONALES
        // ═══════════════════════════════════════════════════════════════

        // Búsqueda textual
        if ($this->search) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$searchTerm}%"));
            });
        }

        // Filtro por categoría
        if ($this->category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $this->category));
        }

        // Filtro por ciudad
        if ($this->city) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $this->city));
        }

        // Filtro "Abierto Ahora"
        if ($this->isOpen) {
            $query->where(function ($q) {
                $q->openNow($this->currentDay, $this->currentTime);
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // ALGORITMO DE PRIORIDAD (MONETIZACIÓN)
        // 1. PRO primero (random entre PROs)
        // 2. Free después (random entre Free)
        // ═══════════════════════════════════════════════════════════════
        $query->orderByDesc('is_pro')
            ->inRandomOrder();

        // Ordenamiento secundario
        if ($this->sort === 'newest') {
            $query->reorder()->orderByDesc('is_pro')->orderByDesc('created_at');
        } elseif ($this->sort === 'name') {
            $query->reorder()->orderByDesc('is_pro')->orderBy('name');
        }

        // ═══════════════════════════════════════════════════════════════
        // EAGER LOADING & PAGINACIÓN
        // ═══════════════════════════════════════════════════════════════
        return $query
            ->with(['category:id,name,slug,color', 'city:id,name,slug'])
            ->select([
                'id', 'name', 'slug', 'logo_path', 'banner_path',
                'is_pro', 'category_id', 'city_id', 'address',
                'created_at', 'hours_data', 'description',
            ])
            ->paginate($this->perPage);
    }

    /**
     * Verifica si un tenant está abierto ahora
     */
    public function isBusinessOpen(Tenant $tenant): bool
    {
        if (empty($tenant->hours_data)) {
            return true; // Asumimos abierto si no hay datos
        }

        $hours = is_array($tenant->hours_data)
            ? $tenant->hours_data
            : json_decode($tenant->hours_data, true);

        if (! $hours) {
            return true;
        }

        // Mapeo de días (Carbon: 0=Domingo, 1=Lunes...)
        $dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $today = $dayNames[$this->currentDay] ?? null;

        if (! $today || ! isset($hours[$today])) {
            return true;
        }

        $todayHours = $hours[$today];

        if (! $todayHours['open'] ?? false) {
            return false;
        }

        $openTime = $todayHours['open_time'] ?? '00:00';
        $closeTime = $todayHours['close_time'] ?? '23:59';

        return $this->currentTime >= $openTime && $this->currentTime <= $closeTime;
    }

    /**
     * Obtiene categorías con conteo
     */
    protected function getCategories()
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->select(['id', 'name', 'slug', 'color', 'icon'])
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)->where('status', 'approved')])
            ->get();
    }

    /**
     * Obtiene ciudades con conteo
     */
    protected function getCities()
    {
        return City::query()
            ->where('is_available', true)
            ->orderBy('name')
            ->select(['id', 'name', 'slug'])
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)->where('status', 'approved')])
            ->get();
    }

    /**
     * Cuenta filtros activos
     */
    protected function getActiveFiltersCount(): int
    {
        $count = 0;
        if ($this->search) {
            $count++;
        }
        if ($this->category) {
            $count++;
        }
        if ($this->city) {
            $count++;
        }
        if ($this->isOpen) {
            $count++;
        }

        return $count;
    }

    /**
     * Obtiene nombre de ciudad seleccionada
     */
    protected function getSelectedCityName(): ?string
    {
        if (! $this->city) {
            return null;
        }

        return City::where('slug', $this->city)->value('name');
    }

    /**
     * Título SEO dinámico
     */
    protected function getPageTitle(): string
    {
        $parts = ['Directorio de Negocios'];

        if ($this->category) {
            $catName = Category::where('slug', $this->category)->value('name');
            if ($catName) {
                $parts = [$catName];
            }
        }

        if ($this->city) {
            $cityName = City::where('slug', $this->city)->value('name');
            if ($cityName) {
                $parts[] = "en {$cityName}";
            }
        }

        return implode(' ', $parts).' | Conecta HN';
    }

    /**
     * Descripción SEO
     */
    protected function getPageDescription(): string
    {
        $base = 'Encuentra los mejores negocios';

        if ($this->category) {
            $catName = Category::where('slug', $this->category)->value('name');
            if ($catName) {
                $base = "Encuentra {$catName}";
            }
        }

        if ($this->city) {
            $cityName = City::where('slug', $this->city)->value('name');
            if ($cityName) {
                $base .= " en {$cityName}";
            }
        }

        return "{$base}. Conecta HN - El directorio más completo de Honduras.";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MÉTODOS DE ACCIÓN
    // ═══════════════════════════════════════════════════════════════════════

    public function selectCategory(string $slug): void
    {
        $this->category = $this->category === $slug ? '' : $slug;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'city', 'isOpen', 'sort']);
        $this->resetPage();
    }

    public function clearFilter(string $filter): void
    {
        $this->reset($filter);
        $this->resetPage();
    }

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    public function openFiltersDrawer(): void
    {
        $this->showFiltersDrawer = true;
        $this->dispatch('open-filters');
    }

    public function closeFiltersDrawer(): void
    {
        $this->showFiltersDrawer = false;
    }

    public function toggleOpenNow(): void
    {
        $this->isOpen = ! $this->isOpen;
        $this->resetPage();
    }

    // Reset de página cuando cambian filtros
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedCity(): void
    {
        $this->resetPage();
    }

    public function updatedIsOpen(): void
    {
        $this->resetPage();
    }
}
