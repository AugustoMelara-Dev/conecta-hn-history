<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TenantRegistrationService - Orchestrates Atomic Tenant Registration
 *
 * 🏗️ Clean Architecture: Encapsulates all registration business logic
 * in a single, testable, reusable service.
 *
 * Benefits:
 * - Testable in isolation (unit tests without HTTP layer)
 * - Reusable for API registration (future)
 * - Single Responsibility: Only handles registration flow
 * - Transaction Safety: Atomic operations with rollback
 *
 * Flow:
 * 1. Validate slug availability (final check before commit)
 * 2. Create User with hashed password
 * 3. Assign role (Spatie/Filament Shield)
 * 4. Create Tenant with plan-based limits
 * 5. Create Payment record (PRO only)
 * 6. Return structured result for routing decision
 */
class TenantRegistrationService
{
    // ═══════════════════════════════════════════════════════════════════════
    // 🛡️ RESERVED SLUGS FIREWALL
    // ═══════════════════════════════════════════════════════════════════════
    protected const RESERVED_SLUGS = [
        // System
        'admin', 'administrator', 'dashboard', 'panel', 'app', 'api', 'system',
        // Auth
        'login', 'logout', 'register', 'registrar', 'signup', 'signin', 'auth',
        // Critical routes
        'directorio', 'directory', 'explorar', 'explore', 'buscar', 'search',
        'categoria', 'category', 'ciudad', 'city', 'ciudades', 'cities',
        // Support
        'soporte', 'support', 'help', 'ayuda', 'contacto', 'contact',
        // Security
        'root', 'superadmin', 'moderator', 'mod', 'staff', 'team',
        // Brand
        'conecta', 'conectahn', 'conecta-hn', 'oficial', 'official',
        // Utilities
        'track', 'tracking', 'webhook', 'webhooks', 'callback', 'oauth',
        'payment', 'payments', 'pago', 'pagos', 'checkout', 'billing',
        // Legal
        'terms', 'privacy', 'legal', 'about', 'nosotros', 'acerca',
        // Impersonation
        'impersonate', 'shadow', 'sudo',
        // Storage
        'storage', 'uploads', 'files', 'images', 'assets', 'public',
        // Testing
        'test', 'demo', 'example', 'sample', 'prueba',
    ];

    /**
     * Registration result DTO
     */
    public User $user;

    public Tenant $tenant;

    public ?Payment $payment = null;

    public bool $hasPaidPlan = false;

    /**
     * 🛡️ Check if slug is reserved by system
     */
    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    /**
     * 🔍 Check slug availability (reserved + database)
     */
    public static function isSlugAvailable(string $slug): bool
    {
        if (self::isReservedSlug($slug)) {
            return false;
        }

        return ! Tenant::where('slug', $slug)->exists();
    }

    /**
     * 💡 Generate smart suggestions for taken slugs
     */
    public static function generateSuggestions(string $baseSlug, int $limit = 3): array
    {
        $candidates = [
            $baseSlug.'-hn',
            $baseSlug.'-'.date('Y'),
            $baseSlug.'-oficial',
            $baseSlug.'-'.Str::random(3),
            $baseSlug.'-'.now()->format('md'),
        ];

        $suggestions = [];

        foreach ($candidates as $candidate) {
            if (self::isSlugAvailable($candidate)) {
                $suggestions[] = $candidate;
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * 🚀 Execute atomic registration flow
     *
     * @throws \Exception If slug is reserved or taken
     * @throws \Throwable If database transaction fails
     */
    public function register(array $data): self
    {
        // ═══════════════════════════════════════════════════════════════════
        // PRE-FLIGHT VALIDATION (Last line of defense)
        // ═══════════════════════════════════════════════════════════════════

        $slug = Str::slug($data['slug'] ?? $data['business_name']);

        if (self::isReservedSlug($slug)) {
            throw new \InvalidArgumentException('El slug está reservado por el sistema.');
        }

        if (Tenant::where('slug', $slug)->exists()) {
            throw new \InvalidArgumentException('El slug ya está en uso.');
        }

        // ═══════════════════════════════════════════════════════════════════
        // ATOMIC TRANSACTION
        // ═══════════════════════════════════════════════════════════════════

        $result = DB::transaction(function () use ($data, $slug) {
            // 1. CREATE USER
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            // 2. ASSIGN ROLE (Spatie Permission / Filament Shield)
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('panel_user');
            }

            // 3. DETERMINE PLAN LIMITS
            $selectedPaidPlan = ($data['selected_plan'] ?? 'free') === 'pro';
            $productLimit = $selectedPaidPlan
                ? ($data['pro_product_limit'] ?? 100)
                : ($data['free_product_limit'] ?? 10);

            // 4. CREATE TENANT
            $tenant = Tenant::create([
                'user_id' => $user->id,
                'name' => $data['business_name'],
                'slug' => $slug,
                'city_id' => $data['city_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'whatsapp_number' => $data['whatsapp'] ?? null,
                'product_limit' => $productLimit,
                // Plan-based state:
                // Free: approved + active + semilla
                // PRO: pending + inactive (until paid) + pro
                'is_active' => ! $selectedPaidPlan,
                'status' => $selectedPaidPlan ? 'pending' : 'approved',
                'plan_type' => $selectedPaidPlan ? TenantPlan::PRO->value : TenantPlan::SEMILLA->value,
                'subscription_status' => 'inactive',
            ]);

            // 5. CREATE PAYMENT (PRO only)
            $payment = null;
            if ($selectedPaidPlan) {
                $payment = Payment::create([
                    'tenant_id' => $tenant->id,
                    'amount' => $data['pro_plan_price'] ?? 500.00,
                    'gateway' => 'bank_transfer',
                    'status' => 'pending',
                    'reference_number' => 'PAY-'.now()->timestamp.'-'.strtoupper(Str::random(6)),
                    'description' => 'Suscripción Plan PRO - '.$data['business_name'],
                ]);
            }

            return [
                'user' => $user,
                'tenant' => $tenant,
                'payment' => $payment,
                'hasPaidPlan' => $selectedPaidPlan,
            ];
        });

        // Populate result properties
        $this->user = $result['user'];
        $this->tenant = $result['tenant'];
        $this->payment = $result['payment'];
        $this->hasPaidPlan = $result['hasPaidPlan'];

        return $this;
    }

    /**
     * 🎯 Get redirect route based on plan
     */
    public function getRedirectRoute(): string
    {
        if ($this->hasPaidPlan && $this->payment) {
            return route('payment.pending', $this->payment->id);
        }

        return '/app/'.$this->tenant->slug;
    }

    /**
     * 📝 Get flash message for session
     */
    public function getFlashMessage(): array
    {
        if ($this->hasPaidPlan) {
            return [
                'type' => 'success',
                'message' => '¡Negocio registrado! Completa el pago para activar tu cuenta.',
            ];
        }

        return [
            'type' => 'success',
            'message' => '🎉 ¡Bienvenido! Tu tienda está lista para configurarse.',
        ];
    }
}
