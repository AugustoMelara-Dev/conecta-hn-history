<?php

namespace App\Livewire\Pages;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Services\TenantRegistrationService;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * RegisterTenant - Experiencia Inmersiva de "Reclamación de Identidad"
 *
 * Embudo de conversión con UX gamificada:
 * 1. Identity Claim: El usuario "reclama" su URL única
 * 2. Credenciales de Usuario
 * 3. Perfil del Negocio
 * 4. Selección de Plan
 *
 * Características:
 * - 🛡️ Firewall de nombres reservados (Blacklist)
 * - 🔍 Validación de disponibilidad en tiempo real
 * - 💡 Motor de sugerencias inteligentes
 * - 🎮 UX gamificada con feedback visual
 */
class RegisterTenant extends Component
{
    // ═══════════════════════════════════════════════════════════════════════
    // 🛡️ FIREWALL: NOMBRES RESERVADOS (BLACKLIST)
    // ═══════════════════════════════════════════════════════════════════════
    protected const RESERVED_SLUGS = [
        // Sistema
        'admin', 'administrator', 'dashboard', 'panel', 'app', 'api', 'system',
        // Auth
        'login', 'logout', 'register', 'registrar', 'signup', 'signin', 'auth',
        // Rutas críticas
        'directorio', 'directory', 'explorar', 'explore', 'buscar', 'search',
        'categoria', 'category', 'ciudad', 'city', 'ciudades', 'cities',
        // Soporte
        'soporte', 'support', 'help', 'ayuda', 'contacto', 'contact',
        // Seguridad
        'root', 'superadmin', 'moderator', 'mod', 'staff', 'team',
        // Marca
        'conecta', 'conectahn', 'conecta-hn', 'oficial', 'official',
        // Utilidades
        'track', 'tracking', 'webhook', 'webhooks', 'callback', 'oauth',
        'payment', 'payments', 'pago', 'pagos', 'checkout', 'billing',
        // Legales
        'terms', 'privacy', 'legal', 'about', 'nosotros', 'acerca',
        // Impersonation
        'impersonate', 'shadow', 'sudo',
        // Storage
        'storage', 'uploads', 'files', 'images', 'assets', 'public',
        // Testing
        'test', 'demo', 'example', 'sample', 'prueba',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // WIZARD STATE
    // ═══════════════════════════════════════════════════════════════════════
    public int $currentStep = 1;

    public int $totalSteps = 4; // Ahora son 4 pasos

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 1: IDENTITY CLAIM (Nuevo paso inmersivo)
    // ═══════════════════════════════════════════════════════════════════════
    public string $business_name = '';

    public string $slug = '';

    public bool $isChecking = false;

    public bool $isAvailable = false;

    public bool $isReserved = false;

    public bool $isTaken = false;

    public string $availabilityMessage = '';

    public array $suggestions = [];

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 2: CAMPOS DE USUARIO
    // ═══════════════════════════════════════════════════════════════════════
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 3: PERFIL DEL NEGOCIO
    // ═══════════════════════════════════════════════════════════════════════
    public ?int $city_id = null;

    public ?int $category_id = null;

    public string $whatsapp = '';

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 4: SELECCIÓN DE PLAN
    // ═══════════════════════════════════════════════════════════════════════
    public string $selectedPlan = 'free';

    // ═══════════════════════════════════════════════════════════════════════
    // ESTADO DEL COMPONENTE
    // ═══════════════════════════════════════════════════════════════════════
    public bool $registrationsEnabled = true;

    public int $freeProductLimit = 10;

    public int $proProductLimit = 100;

    public float $proPlanPrice = 500.00;

    public bool $isSubmitting = false;

    /**
     * Inicialización con verificación de Gatekeeper
     */
    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->registrationsEnabled = $settings->allow_registrations ?? true;
        $this->freeProductLimit = $settings->free_plan_product_limit ?? 10;
        $this->proProductLimit = $settings->pro_plan_product_limit ?? 100;
        $this->proPlanPrice = $settings->pro_plan_price ?? 500.00;

        // Redirigir si ya está autenticado
        if (Auth::check()) {
            $this->redirect('/app');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 🔍 MOTOR DE VALIDACIÓN EN TIEMPO REAL
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Hook: Se ejecuta cuando el nombre del negocio cambia
     * Genera slug y valida disponibilidad en tiempo real
     */
    public function updatedBusinessName(string $value): void
    {
        // Resetear estados
        $this->resetAvailabilityState();

        // Generar slug limpio
        $this->slug = Str::slug($value);

        // No validar si está vacío o muy corto
        if (strlen($this->slug) < 3) {
            $this->availabilityMessage = '';

            return;
        }

        // Ejecutar validación
        $this->checkAvailability();
    }

    /**
     * Hook: Se ejecuta cuando el slug cambia directamente
     */
    public function updatedSlug(string $value): void
    {
        // Limpiar y slugify
        $this->slug = Str::slug($value);

        if (strlen($this->slug) < 3) {
            $this->resetAvailabilityState();

            return;
        }

        $this->checkAvailability();
    }

    /**
     * Resetea el estado de disponibilidad
     */
    protected function resetAvailabilityState(): void
    {
        $this->isAvailable = false;
        $this->isReserved = false;
        $this->isTaken = false;
        $this->suggestions = [];
        $this->availabilityMessage = '';
    }

    /**
     * 🔍 Motor principal de validación de disponibilidad
     */
    public function checkAvailability(): void
    {
        $this->isChecking = true;
        $this->resetAvailabilityState();

        // 1. 🛡️ FIREWALL: Verificar blacklist primero
        if ($this->isReservedSlug($this->slug)) {
            $this->isReserved = true;
            $this->availabilityMessage = '🚫 Esta dirección está reservada por el sistema';
            $this->isChecking = false;

            return;
        }

        // 2. 🗃️ Verificar en base de datos
        if (Tenant::where('slug', $this->slug)->exists()) {
            $this->isTaken = true;
            $this->availabilityMessage = '❌ Esta dirección ya está en uso';
            $this->generateSuggestions();
            $this->isChecking = false;

            return;
        }

        // 3. ✅ Disponible
        $this->isAvailable = true;
        $this->availabilityMessage = '✅ ¡Esta dirección está disponible!';
        $this->isChecking = false;
    }

    protected function isReservedSlug(string $slug): bool
    {
        return TenantRegistrationService::isReservedSlug($slug);
    }

    protected function generateSuggestions(): void
    {
        $this->suggestions = TenantRegistrationService::generateSuggestions($this->slug, 3);
    }

    /**
     * Seleccionar una sugerencia con un clic
     */
    public function selectSuggestion(string $suggestion): void
    {
        $this->slug = $suggestion;
        $this->business_name = str_replace('-', ' ', $suggestion);
        $this->checkAvailability();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VALIDACIÓN DE OTROS CAMPOS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Validación en tiempo real del email
     */
    public function updatedEmail(): void
    {
        $this->validateOnly('email', [
            'email' => 'required|email|unique:users,email',
        ]);
    }

    /**
     * Reglas de validación por paso
     */
    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'business_name' => 'required|string|min:3|max:255',
                'slug' => 'required|alpha_dash|min:3|max:50',
            ],
            2 => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
            3 => [
                'city_id' => 'required|exists:cities,id',
                'category_id' => 'required|exists:categories,id',
                'whatsapp' => 'nullable|string|max:20',
            ],
            4 => [
                'selectedPlan' => 'required|in:free,pro',
            ],
            default => [],
        };
    }

    /**
     * Mensajes de validación personalizados
     */
    protected function messages(): array
    {
        return [
            'business_name.required' => 'Ingresa el nombre de tu negocio.',
            'business_name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'slug.required' => 'La URL de tu negocio es requerida.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, números y guiones.',
            'email.unique' => 'Este correo ya está registrado.',
            'city_id.required' => 'Selecciona una ciudad.',
            'category_id.required' => 'Selecciona una categoría.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    /**
     * Avanzar al siguiente paso
     */
    public function nextStep(): void
    {
        // Validación especial para paso 1 (Identity Claim)
        if ($this->currentStep === 1) {
            if (! $this->isAvailable) {
                $this->addError('slug', 'Debes elegir una dirección disponible antes de continuar.');

                return;
            }

            // Verificar una última vez antes de continuar
            if ($this->isReservedSlug($this->slug)) {
                $this->addError('slug', 'Esta dirección está reservada por el sistema.');

                return;
            }

            if (Tenant::where('slug', $this->slug)->exists()) {
                $this->addError('slug', 'Esta dirección acaba de ser tomada. Elige otra.');
                $this->checkAvailability();

                return;
            }
        }

        // Validar paso actual
        $this->validate($this->rulesForStep($this->currentStep), $this->messages());

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    /**
     * Retroceder al paso anterior
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Ir a un paso específico
     */
    public function goToStep(int $step): void
    {
        // Solo permitir ir a pasos ya completados o el actual
        if ($step <= $this->currentStep && $step >= 1) {
            $this->currentStep = $step;
        }
    }

    /**
     * Proceso de registro con transacción atómica
     */
    public function register(): void
    {
        // Verificar gatekeeper
        if (! $this->registrationsEnabled) {
            session()->flash('error', 'Los registros están temporalmente deshabilitados.');

            return;
        }

        // Validación final de slug (doble verificación de seguridad)
        if ($this->isReservedSlug($this->slug)) {
            $this->addError('slug', 'Esta dirección está reservada.');

            return;
        }

        if (Tenant::where('slug', $this->slug)->exists()) {
            $this->addError('slug', 'Esta dirección ya está ocupada.');

            return;
        }

        // Validar todos los pasos primero
        $allRules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4)
        );
        $this->validate($allRules, $this->messages());

        $this->isSubmitting = true;

        try {
            // ═══════════════════════════════════════════════════════════════
            // DELEGATE TO SERVICE (Clean Architecture)
            // ═══════════════════════════════════════════════════════════════
            $service = new TenantRegistrationService;
            $service->register([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'business_name' => $this->business_name,
                'slug' => $this->slug,
                'city_id' => $this->city_id,
                'category_id' => $this->category_id,
                'whatsapp' => $this->whatsapp,
                'selected_plan' => $this->selectedPlan,
                'free_product_limit' => $this->freeProductLimit,
                'pro_product_limit' => $this->proProductLimit,
                'pro_plan_price' => $this->proPlanPrice,
            ]);

            // ═══════════════════════════════════════════════════════════════
            // POST-REGISTRATION ROUTING
            // ═══════════════════════════════════════════════════════════════
            $flash = $service->getFlashMessage();
            session()->flash($flash['type'], $flash['message']);

            if (! $service->isPro) {
                Auth::login($service->user);
            }

            $this->redirect($service->getRedirectRoute());

        } catch (\InvalidArgumentException $e) {
            $this->isSubmitting = false;
            $this->addError('slug', $e->getMessage());
        } catch (\Exception $e) {
            $this->isSubmitting = false;
            session()->flash('error', 'Ocurrió un error al crear tu cuenta. Intenta nuevamente.');
            report($e);
        }
    }

    /**
     * Renderiza la vista
     */
    public function render()
    {
        return view('livewire.pages.register-tenant', [
            'cities' => City::where('is_available', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
        ])->layout('layouts.guest', [
            'title' => 'Registrar Negocio | Conecta HN',
        ]);
    }
}
