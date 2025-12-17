<?php

namespace App\Livewire\Pages;

use App\Models\Category;
use App\Models\City;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * RegisterTenant - Wizard de Registro Multi-Paso
 * 
 * Embudo de conversión con 3 pasos:
 * 1. Credenciales de Usuario
 * 2. Perfil del Negocio
 * 3. Selección de Plan
 * 
 * Características:
 * - Gatekeeper: Verifica allow_registrations
 * - Transacción atómica: User + Tenant + Role + Payment
 * - Enrutamiento inteligente: Free → Dashboard, PRO → Pago Pendiente
 */
class RegisterTenant extends Component
{
    // ═══════════════════════════════════════════════════════════════════════
    // WIZARD STATE
    // ═══════════════════════════════════════════════════════════════════════
    public int $currentStep = 1;
    public int $totalSteps = 3;

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 1: CAMPOS DE USUARIO
    // ═══════════════════════════════════════════════════════════════════════
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 2: CAMPOS DEL NEGOCIO
    // ═══════════════════════════════════════════════════════════════════════
    public string $business_name = '';
    public string $slug = '';
    public ?int $city_id = null;
    public ?int $category_id = null;
    public string $whatsapp = '';

    // ═══════════════════════════════════════════════════════════════════════
    // PASO 3: SELECCIÓN DE PLAN
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

    /**
     * Genera slug automáticamente basado en el nombre del negocio
     */
    public function updatedBusinessName(string $value): void
    {
        $baseSlug = Str::slug($value);
        
        // Verificar unicidad y agregar sufijo si es necesario
        if (Tenant::where('slug', $baseSlug)->exists()) {
            $this->slug = $baseSlug . '-' . Str::random(4);
        } else {
            $this->slug = $baseSlug;
        }
    }

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
     * Validación en tiempo real del slug
     */
    public function updatedSlug(): void
    {
        $this->slug = Str::slug($this->slug);
        $this->validateOnly('slug', [
            'slug' => 'required|alpha_dash|unique:tenants,slug|min:3|max:50',
        ]);
    }

    /**
     * Reglas de validación por paso
     */
    protected function rulesForStep(int $step): array
    {
        return match($step) {
            1 => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
            2 => [
                'business_name' => 'required|string|max:255',
                'slug' => 'required|alpha_dash|unique:tenants,slug|min:3|max:50',
                'city_id' => 'required|exists:cities,id',
                'category_id' => 'required|exists:categories,id',
                'whatsapp' => 'nullable|string|max:20',
            ],
            3 => [
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
            'email.unique' => 'Este correo ya está registrado.',
            'slug.unique' => 'Esta URL ya está en uso. Elige otra.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, números y guiones.',
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
        if (!$this->registrationsEnabled) {
            session()->flash('error', 'Los registros están temporalmente deshabilitados.');
            return;
        }

        // Validar todos los pasos
        $allRules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3)
        );
        $this->validate($allRules, $this->messages());

        $this->isSubmitting = true;

        try {
            // ═══════════════════════════════════════════════════════════════
            // TRANSACCIÓN ATÓMICA: User + Tenant + Role + Payment
            // ═══════════════════════════════════════════════════════════════
            $result = DB::transaction(function () {
                // 1. Crear Usuario
                $user = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => Hash::make($this->password),
                    'email_verified_at' => now(),
                ]);

                // 2. Asignar Rol (Spatie Permission / Filament Shield)
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole('panel_user');
                }

                // 3. Determinar límites y estado según plan
                $isPro = $this->selectedPlan === 'pro';
                $productLimit = $isPro ? $this->proProductLimit : $this->freeProductLimit;

                // 4. Crear Tenant
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'name' => $this->business_name,
                    'slug' => $this->slug,
                    'city_id' => $this->city_id,
                    'category_id' => $this->category_id,
                    'whatsapp_number' => $this->whatsapp,
                    'product_limit' => $productLimit,
                    // Estado según plan:
                    // Free: approved + active + !pro
                    // PRO: pending + inactive (hasta pagar) + pro
                    'is_active' => !$isPro, // Free = activo inmediato
                    'is_pro' => $isPro,
                    'status' => $isPro ? 'pending' : 'approved',
                ]);

                // 5. Crear Pago (Solo PRO)
                $payment = null;
                if ($isPro) {
                    $payment = Payment::create([
                        'tenant_id' => $tenant->id,
                        'amount' => $this->proPlanPrice,
                        'gateway' => 'bank_transfer',
                        'status' => 'pending',
                        'reference_number' => 'PAY-' . now()->timestamp . '-' . strtoupper(Str::random(6)),
                        'description' => 'Suscripción Plan PRO - ' . $this->business_name,
                    ]);
                }

                return [
                    'user' => $user,
                    'tenant' => $tenant,
                    'payment' => $payment,
                    'isPro' => $isPro,
                ];
            });

            // ═══════════════════════════════════════════════════════════════
            // ENRUTAMIENTO POST-REGISTRO (The Fork)
            // ═══════════════════════════════════════════════════════════════
            if ($result['isPro']) {
                // Ruta PRO: NO autenticar, ir a instrucciones de pago
                session()->flash('success', '¡Negocio registrado! Completa el pago para activar tu cuenta.');
                $this->redirect(route('payment.pending', $result['payment']->id));
            } else {
                // Ruta Free: Autenticar y ir al dashboard
                Auth::login($result['user']);
                session()->flash('success', '¡Bienvenido a Conecta HN! Tu negocio ha sido creado.');
                $this->redirect('/app/' . $result['tenant']->slug);
            }

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
