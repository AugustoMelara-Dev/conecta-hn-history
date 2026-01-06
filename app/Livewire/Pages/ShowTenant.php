<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Carbon\Carbon;
use Livewire\Component;

/**
 * ShowTenant - Perfil del Negocio (Microsite)
 *
 * Interfaz Polimórfica que se adapta automáticamente:
 * - FREE: Horario simple, sin mapa embebido, redes limitadas
 * - PRO: Horario estructurado con "Abierto/Cerrado", mapa GPS, redes completas
 *
 * Mobile-First | Conversion-Focused | SEO Optimized
 */
class ShowTenant extends Component
{
    /**
     * Modelo del negocio
     */
    public Tenant $tenant;

    /**
     * Pestaña activa (info, gallery, products)
     */
    public string $activeTab = 'info';

    /**
     * Estado de apertura calculado
     * null = sin datos estructurados (FREE), true = abierto, false = cerrado
     */
    public ?bool $isOpen = null;

    /**
     * Hora de cierre hoy
     */
    public ?string $closesAt = null;

    /**
     * Hora de apertura hoy
     */
    public ?string $opensAt = null;

    /**
     * Horario formateado para hoy
     */
    public ?string $todayHoursFormatted = null;

    /**
     * Día actual de la semana
     */
    public string $currentDayName;

    /**
     * Modo preview (para dueño/admin)
     */
    public bool $previewMode = false;

    /**
     * Razón del modo preview
     */
    public ?string $previewReason = null;

    /**
     * Producto seleccionado para modal
     */
    public $selectedProduct = null;

    /**
     * Mostrar modal de producto
     */
    public bool $showProductModal = false;

    /**
     * ID de la sección activa para filtrado de productos (PRO)
     * null = Ver todos los productos (sin filtro)
     */
    public ?int $activeSectionId = null;

    /**
     * Colección de secciones del menú (PRO)
     */
    public $menuSections = [];

    /**
     * ¿Tiene datos de horario estructurados? (PRO feature)
     */
    public bool $hasStructuredHours = false;

    /**
     * ¿Tiene coordenadas GPS? (PRO feature)
     */
    public bool $hasGpsCoordinates = false;

    /**
     * Inicialización del componente con Route Model Binding
     *
     * AJUSTES CRÍTICOS SaaS:
     * 1. Seguridad: Bloquear tenants inactivos
     * 2. Analítica: Contador de visitas
     * 3. Preview Mode: Permitir a admins ver tenants no publicados
     */
    public function mount(Tenant $tenant): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // 1. SEGURIDAD: Detectar modo preview PRIMERO
        // ═══════════════════════════════════════════════════════════════════
        $this->previewMode = session('tenant_preview_mode', false);
        $this->previewReason = session('tenant_preview_reason');

        // ═══════════════════════════════════════════════════════════════════
        // 2. BLOQUEO DE TENANTS INACTIVOS/PENDIENTES
        // Si is_active = false y no estamos en preview, denegar acceso
        // Esto evita que enlaces directos muestren negocios no publicados
        // ═══════════════════════════════════════════════════════════════════
        if (! $tenant->is_active && ! $this->previewMode) {
            abort(404, 'Este negocio no está disponible actualmente.');
        }

        // Eager Loading optimizado
        $this->tenant = $tenant->load([
            'category:id,name,slug,color',
            'city:id,name,slug',
            'products' => fn ($q) => $q->where('is_active', true)->orderBy('created_at', 'desc'),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // 3. ANALÍTICA: Registrar visita (Atomic Increment)
        // Solo si NO estamos en preview (evita inflar estadísticas)
        // increment() no dispara eventos de modelo = más rápido
        // ═══════════════════════════════════════════════════════════════════
        if (! $this->previewMode) {
            Tenant::where('id', $this->tenant->id)->increment('views');
        }

        // Detectar características del tenant
        $this->hasGpsCoordinates = ! empty($this->tenant->latitude) && ! empty($this->tenant->longitude);
        $this->hasStructuredHours = $this->detectStructuredHours();

        // ═══════════════════════════════════════════════════════════════════
        // INICIALIZACIÓN DE SECCIONES (PRO Feature)
        // ═══════════════════════════════════════════════════════════════════
        // REGLA 1: COLAPSO ESTRUCTURAL (Structural Flattening)
        // Los usuarios FREE no tienen acceso a organización por secciones
        // ═══════════════════════════════════════════════════════════════════
        if ($this->tenant->is_pro) {
            // PRO: Cargar secciones activas ordenadas por sort_order
            $this->menuSections = $this->tenant->menuSections()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['products' => fn ($q) => $q->where('is_visible', true)])
                ->get();

            // Inicializar con primera sección si existe
            if ($this->menuSections->count() > 0) {
                $this->activeSectionId = $this->menuSections->first()->id;
            }
        } else {
            // ═══ FREE: COLAPSO ESTRUCTURAL FORZADO ═══
            // Ignorar toda estructura organizativa guardada en DB
            // Esto elimina la barra de tabs y muestra lista plana
            $this->menuSections = collect(); // Colección vacía explícita
            $this->activeSectionId = null;   // Sin filtro de sección
        }

        // Calcular estado de apertura (solo si tiene datos estructurados)
        $this->calculateOpenStatus();
    }

    /**
     * Renderiza la vista con metadatos SEO
     */
    public function render()
    {
        return view('livewire.pages.show-tenant', [
            'products' => $this->getProducts(),
            'gallery' => $this->getGallery(),
            'features' => $this->getFeatures(),
            'socialLinks' => $this->getSocialLinks(),
            'formattedHours' => $this->getFormattedHours(),
            'scheduleDisplay' => $this->getScheduleDisplay(),
            'mapEmbedUrl' => $this->getMapEmbedUrl(),
            'menuSections' => $this->menuSections,
        ])->layout('layouts.public', [
            'title' => $this->getPageTitle(),
            'description' => $this->getPageDescription(),
            'ogImage' => $this->getOgImage(),
            'ogType' => 'business.business',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES - Lógica de Negocio
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Detecta si tiene datos de horario estructurados (array con días)
     */
    protected function detectStructuredHours(): bool
    {
        $hours = $this->tenant->hours_data;

        if (empty($hours)) {
            return false;
        }

        // Normalizar a array
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }

        // Verificar que sea un array con al menos un día
        return is_array($hours) && count($hours) > 0;
    }

    /**
     * Calcula si el negocio está abierto ahora
     * Solo funciona con datos estructurados (PRO)
     *
     * IMPORTANTE: Usamos zona horaria de Honduras (America/Tegucigalpa)
     * para evitar desfases cuando el servidor está en UTC
     */
    protected function calculateOpenStatus(): void
    {
        // Forzar zona horaria de Honduras/Centroamérica
        $now = Carbon::now('America/Tegucigalpa');
        $this->currentDayName = $this->getDayName($now->dayOfWeek);

        // Si no tiene horarios estructurados, retornar null (no podemos calcular)
        if (! $this->hasStructuredHours) {
            $this->isOpen = null;
            $this->todayHoursFormatted = null;

            return;
        }

        $hours = $this->tenant->hours_data;

        // Normalizar a array
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }

        if (! $hours) {
            $this->isOpen = null;

            return;
        }

        // Buscar el día actual en el array (formato del Repeater)
        $todayData = null;
        foreach ($hours as $dayEntry) {
            if (isset($dayEntry['day']) && $dayEntry['day'] === $this->currentDayName) {
                $todayData = $dayEntry;
                break;
            }
        }

        // Si no hay datos para hoy
        if (! $todayData) {
            $this->isOpen = null;
            $this->todayHoursFormatted = 'Sin horario definido';

            return;
        }

        // Verificar si está cerrado hoy
        if (! empty($todayData['is_closed'])) {
            $this->isOpen = false;
            $this->todayHoursFormatted = 'Cerrado hoy';

            return;
        }

        // Obtener horarios
        $openTime = $todayData['open'] ?? '08:00';
        $closeTime = $todayData['close'] ?? '18:00';

        $this->opensAt = $openTime;
        $this->closesAt = $closeTime;

        // Calcular estado
        $currentTime = $now->format('H:i');
        $this->isOpen = $currentTime >= $openTime && $currentTime <= $closeTime;

        // Formatear para mostrar
        $this->todayHoursFormatted = "{$this->formatTime($openTime)} - {$this->formatTime($closeTime)}";
    }

    /**
     * Formatea hora en formato legible
     */
    protected function formatTime(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', $time)->format('g:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * Obtiene nombre del día en español
     */
    protected function getDayName(int $dayOfWeek): string
    {
        $days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];

        return $days[$dayOfWeek] ?? 'lunes';
    }

    /**
     * Obtiene la presentación del horario (polimórfico FREE/PRO)
     */
    protected function getScheduleDisplay(): array
    {
        // PRO: Datos estructurados
        if ($this->hasStructuredHours) {
            return [
                'type' => 'structured',
                'data' => $this->getFormattedHours(),
                'isOpen' => $this->isOpen,
                'todayFormatted' => $this->todayHoursFormatted,
            ];
        }

        // FREE: Texto simple
        $simpleSchedule = $this->tenant->simple_schedule;

        return [
            'type' => 'simple',
            'data' => $simpleSchedule ?: 'Horario no especificado',
            'isOpen' => null, // No podemos calcular con texto simple
        ];
    }

    /**
     * Genera URL para iframe de Google Maps Embed
     * Solo disponible si tiene coordenadas GPS (PRO)
     */
    public function getMapEmbedUrl(): ?string
    {
        if (! $this->hasGpsCoordinates) {
            return null;
        }

        $lat = $this->tenant->latitude;
        $lng = $this->tenant->longitude;

        return "https://maps.google.com/maps?q={$lat},{$lng}&t=&z=15&ie=UTF8&iwloc=&output=embed";
    }

    /**
     * Obtiene redes sociales como colección limpia con iconos
     */
    protected function getSocialLinks(): array
    {
        $links = [];

        $networks = [
            'facebook' => [
                'url' => $this->tenant->facebook_url,
                'name' => 'Facebook',
                'icon' => 'facebook',
                'color' => 'bg-blue-100 text-blue-600 hover:bg-blue-200',
            ],
            'instagram' => [
                'url' => $this->tenant->instagram_url,
                'name' => 'Instagram',
                'icon' => 'instagram',
                'color' => 'bg-pink-100 text-pink-600 hover:bg-pink-200',
            ],
            'tiktok' => [
                'url' => $this->tenant->tiktok_url,
                'name' => 'TikTok',
                'icon' => 'tiktok',
                'color' => 'bg-gray-100 text-gray-800 hover:bg-gray-200',
            ],
            'website' => [
                'url' => $this->tenant->website_url,
                'name' => 'Sitio Web',
                'icon' => 'globe',
                'color' => 'bg-gray-100 text-gray-800 hover:bg-gray-200',
            ],
        ];

        foreach ($networks as $key => $network) {
            if (! empty($network['url'])) {
                $links[] = [
                    'key' => $key,
                    'url' => $network['url'],
                    'name' => $network['name'],
                    'icon' => $network['icon'],
                    'color' => $network['color'],
                ];
            }
        }

        return $links;
    }

    /**
     * Obtiene horario formateado completo (para tabla PRO)
     */
    protected function getFormattedHours(): array
    {
        if (! $this->hasStructuredHours) {
            return [];
        }

        $hours = $this->tenant->hours_data;

        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }

        if (! $hours || ! is_array($hours)) {
            return [];
        }

        $displayNames = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado' => 'Sábado',
            'domingo' => 'Domingo',
        ];

        $daysOrder = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $formatted = [];

        // Crear mapa de días desde el array del Repeater
        $dayMap = [];
        foreach ($hours as $entry) {
            if (isset($entry['day'])) {
                $dayMap[$entry['day']] = $entry;
            }
        }

        foreach ($daysOrder as $day) {
            if (! isset($dayMap[$day])) {
                continue;
            }

            $dayData = $dayMap[$day];
            $isClosed = ! empty($dayData['is_closed']);

            $formatted[] = [
                'day' => $displayNames[$day] ?? ucfirst($day),
                'dayKey' => $day,
                'isToday' => $day === $this->currentDayName,
                'isClosed' => $isClosed,
                'hours' => $isClosed
                    ? 'Cerrado'
                    : $this->formatTime($dayData['open'] ?? '08:00').' - '.$this->formatTime($dayData['close'] ?? '18:00'),
            ];
        }

        return $formatted;
    }

    /**
     * Obtiene productos con restricciones de plan
     *
     * REGLAS DE NEGOCIO:
     * - REGLA 2: GUILLOTINA DE INVENTARIO - FREE limitado a X productos
     * - REGLA 3: DESACTIVACIÓN DE FILTROS - Solo PRO puede filtrar por sección
     */
    protected function getProducts()
    {
        // Base query: productos visibles del tenant
        $query = $this->tenant->products()
            ->where('is_visible', true)
            ->orderBy('name');

        // ═══════════════════════════════════════════════════════════
        // LÓGICA DIFERENCIADA POR PLAN
        // ═══════════════════════════════════════════════════════════
        if ($this->tenant->is_pro) {
            // PRO: Acceso completo + Filtrado por sección
            // REGLA 3: Solo PRO puede usar filtros de sección
            if ($this->activeSectionId) {
                $query->where('menu_section_id', $this->activeSectionId);
            }

            // PRO: Sin límite de productos
            return $query->get();
        } else {
            // ═══ FREE: GUILLOTINA DE INVENTARIO ═══
            // REGLA 2: Límite duro de productos desde config
            // Si tenía 80 productos, solo mostrará los primeros N
            // Los demás "desaparecen" de la vista pública
            try {
                $settings = app(GeneralSettings::class);
                $limit = $settings->free_plan_product_limit ?? 10;
            } catch (\Exception $e) {
                $limit = 10; // Fallback seguro
            }

            // Ignorar cualquier intento de filtrar por sección
            // REGLA 3: Protección contra manipulación de estado
            // $this->activeSectionId es ignorado completamente para FREE

            return $query->take($limit)->get();
        }
    }

    /**
     * Cambia la sección activa (llamado desde Livewire)
     */
    public function setActiveSection(?int $sectionId): void
    {
        $this->activeSectionId = $sectionId;
    }

    /**
     * Obtiene galería de imágenes
     */
    protected function getGallery()
    {
        return collect();
    }

    /**
     * Obtiene características/amenidades
     */
    protected function getFeatures(): array
    {
        $features = $this->tenant->features ?? [];

        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        return $features;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // URL BUILDERS - Generadores de enlaces CON TRACKING
    // Estas URLs pasan por InteractionController para registrar clics
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Genera URL de WhatsApp CON TRACKING
     *
     * En lugar de devolver wa.me directo, devolvemos la ruta de tracking
     * que registra el clic y luego redirige a WhatsApp.
     */
    public function getWhatsAppUrl(?string $productName = null): string
    {
        $phone = $this->tenant->whatsapp_number ?? $this->tenant->phone ?? '';

        if (empty($phone)) {
            return '#';
        }

        // Construir mensaje
        $customMessage = $this->tenant->whatsapp_message;

        if ($customMessage && $productName) {
            $message = str_replace(
                ['{producto}', '{precio}'],
                [$productName, ''],
                $customMessage
            );
        } elseif ($productName) {
            $message = "Hola, vi su negocio en Conecta HN y me interesa: {$productName}";
        } elseif ($customMessage) {
            $message = str_replace(['{producto}', '{precio}'], ['', ''], $customMessage);
        } else {
            $message = "Hola, vi su negocio *{$this->tenant->name}* en Conecta HN y me gustaría más información.";
        }

        // TRACKING: Usar ruta interna que registra el clic
        return route('track.whatsapp', [
            'tenant' => $this->tenant->slug,
            'text' => trim($message),
        ]);
    }

    /**
     * Genera URL para llamada telefónica CON TRACKING
     */
    public function getPhoneUrl(): string
    {
        $phone = $this->tenant->phone ?? $this->tenant->whatsapp_number ?? '';

        if (empty($phone)) {
            return '#';
        }

        // TRACKING: Usar ruta interna que registra el clic
        return route('track.call', ['tenant' => $this->tenant->slug]);
    }

    /**
     * Genera URL para navegación GPS CON TRACKING
     */
    public function getMapsUrl(): string
    {
        if (! $this->hasGpsCoordinates && empty($this->tenant->address)) {
            return '#';
        }

        // TRACKING: Usar ruta interna que registra el clic
        return route('track.maps', ['tenant' => $this->tenant->slug]);
    }

    /**
     * Genera URL para Waze (Sin tracking por ahora)
     */
    public function getWazeUrl(): string
    {
        if ($this->hasGpsCoordinates) {
            return "https://waze.com/ul?ll={$this->tenant->latitude},{$this->tenant->longitude}&navigate=yes";
        }

        return $this->getMapsUrl();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MODAL ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Abre modal de producto
     */
    public function openProductModal($productId): void
    {
        $this->selectedProduct = $this->tenant->products->find($productId);
        $this->showProductModal = true;
    }

    /**
     * Cierra modal de producto
     */
    public function closeProductModal(): void
    {
        $this->showProductModal = false;
        $this->selectedProduct = null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SEO METHODS
    // ═══════════════════════════════════════════════════════════════════════

    protected function getPageTitle(): string
    {
        $parts = [$this->tenant->name];

        if ($this->tenant->category) {
            $parts[] = $this->tenant->category->name;
        }

        if ($this->tenant->city) {
            $parts[] = $this->tenant->city->name;
        }

        return implode(' - ', $parts).' | Conecta HN';
    }

    protected function getPageDescription(): string
    {
        if ($this->tenant->description) {
            return \Illuminate\Support\Str::limit(strip_tags($this->tenant->description), 160);
        }

        $desc = $this->tenant->name;
        if ($this->tenant->category) {
            $desc .= " - {$this->tenant->category->name}";
        }
        if ($this->tenant->city) {
            $desc .= " en {$this->tenant->city->name}";
        }

        return "{$desc}. Encuentra los mejores negocios en Conecta HN.";
    }

    protected function getOgImage(): ?string
    {
        if ($this->tenant->banner_path) {
            return asset('storage/'.$this->tenant->banner_path);
        }

        if ($this->tenant->logo_path) {
            return asset('storage/'.$this->tenant->logo_path);
        }

        return null;
    }
}
