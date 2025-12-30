<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

/**
 * RegisterTenantController - Atomic Business Registration Engine
 *
 * ARCHITECTURE:
 * - All database operations wrapped in DB::transaction()
 * - Rollback on ANY failure (no orphan users)
 * - Deterministic slug generation with collision handling
 * - Immediate authentication after successful registration
 *
 * SECURITY:
 * - Password hashing via bcrypt (handled by User model cast)
 * - File upload validation (logo)
 * - Rate limiting recommended via middleware
 */
class RegisterTenantController extends Controller
{
    /**
     * Display the tenant registration form.
     */
    public function create()
    {
        // SELF-HEALING CACHE: 300s TTL as fallback (Observers handle real-time invalidation)
        // Short TTL minimizes stale data window if Observer fails (e.g., direct DB manipulation)
        return Inertia::render('Auth/RegisterTenant', [
            'categories' => Cache::remember(
                'registration_categories',
                300, // 5 minutes - safety fallback only
                fn () => \App\Models\Category::orderBy('name')->get(['id', 'name', 'slug'])
            ),
            'cities' => Cache::remember(
                'registration_cities',
                300, // 5 minutes - safety fallback only
                fn () => \App\Models\City::orderBy('name')->get(['id', 'name', 'slug'])
            ),
        ]);
    }

    /**
     * Handle a new tenant registration request.
     *
     * SRE ANALYSIS: Semantically Aware Concurrency Control
     * 
     * This implementation eliminates the "Blind Retry" anti-pattern by:
     * 1. SEMANTIC DISCRIMINATION: Inspects exception message to distinguish
     *    slug collisions (retryable) from email collisions (non-retryable)
     * 2. FAST-FAIL: Email collisions exit immediately — retrying with new
     *    slug will never resolve an email constraint violation
     * 3. OPTIMIZED RETRY: On attempts > 1, skips the SELECT query in
     *    ensureUniqueSlug since high-entropy suffix is applied directly
     * 4. LINEAR BACKOFF: 50ms * attempt reduces thundering herd effects
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // ═══════════════════════════════════════════════════════════════════
        // STEP 1: Validation (Before retry loop - only validate once)
        // ═══════════════════════════════════════════════════════════════════
        $validated = $request->validate([
            // Business Info
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],

            // Owner Info
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Terms
            'terms_accepted' => ['required', 'accepted'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // STEP 2: Self-Healing Transactional Loop with Semantic Discrimination
        // ═══════════════════════════════════════════════════════════════════
        $baseSlug = $this->generateSlug($validated['name']);
        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        do {
            $attempt++;
            
            // ENTROPY INJECTION: On retry, append random suffix to avoid collision
            // On subsequent attempts, we trust the entropy and skip ensureUniqueSlug
            $currentSlug = $attempt === 1 
                ? $baseSlug 
                : $baseSlug . '-' . Str::random(4);

            try {
                $result = DB::transaction(function () use ($validated, $currentSlug, $request, $attempt) {
                    // OPTIMIZATION: Only check uniqueness on first attempt
                    // Subsequent attempts use high-entropy suffix, rely on DB constraint
                    $slug = $attempt === 1 
                        ? $this->ensureUniqueSlug($currentSlug)
                        : $currentSlug;

                    // Create User
                    $user = User::create([
                        'name' => $validated['owner_name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'role' => 'seller',
                    ]);

                    // Create Tenant (linked to user)
                    $tenant = Tenant::create([
                        'name' => $validated['name'],
                        'slug' => $slug,
                        'user_id' => $user->id,
                        'category_id' => $validated['category_id'],
                        'city_id' => $validated['city_id'],
                        'description' => $validated['description'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                        'address' => $validated['address'] ?? null,
                        'status' => 'active',
                        'is_active' => true,
                        'plan_type' => 'semilla',
                        'subscription_status' => 'active',
                    ]);

                    // Handle logo upload
                    if ($request->hasFile('logo')) {
                        $logoPath = $request->file('logo')->store('tenants/logos', 'public');
                        $tenant->update(['logo_path' => $logoPath]);
                    }

                    // Assign role if using Spatie Permissions
                    if (method_exists($user, 'assignRole')) {
                        $user->assignRole('seller');
                    }

                    return compact('user', 'tenant', 'slug');
                });

                // ═══════════════════════════════════════════════════════════════
                // SUCCESS: Post-Transaction Actions
                // ═══════════════════════════════════════════════════════════════
                event(new Registered($result['user']));
                Auth::login($result['user']);

                Log::info('New tenant registered', [
                    'user_id' => $result['user']->id,
                    'tenant_id' => $result['tenant']->id,
                    'tenant_slug' => $result['slug'],
                    'attempts' => $attempt,
                ]);

                return redirect()->intended(route('tenant.dashboard'));

            } catch (\Illuminate\Database\QueryException $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();
                
                // ═══════════════════════════════════════════════════════════════
                // SEMANTIC DISCRIMINATION: Identify collision type
                // ═══════════════════════════════════════════════════════════════
                $isDuplicateEntry = $e->getCode() === '23000' || 
                                    str_contains($errorMessage, 'Duplicate entry');
                
                if (!$isDuplicateEntry) {
                    // Non-recoverable database error - throw immediately
                    Log::error('Registration failed: Non-recoverable DB error', [
                        'email' => $validated['email'],
                        'error' => $errorMessage,
                        'code' => $e->getCode(),
                    ]);
                    
                    return back()
                        ->withInput($request->except('password', 'password_confirmation'))
                        ->withErrors(['email' => 'Error al procesar el registro. Por favor intenta de nuevo.']);
                }

                // ═══════════════════════════════════════════════════════════════
                // FAST-FAIL: Email collision cannot be resolved by slug change
                // ═══════════════════════════════════════════════════════════════
                $isEmailCollision = str_contains($errorMessage, 'users_email_unique') ||
                                    str_contains($errorMessage, "'email'") ||
                                    str_contains($errorMessage, 'users.email');
                
                if ($isEmailCollision) {
                    Log::warning('Registration failed: Email collision (race condition)', [
                        'email' => $validated['email'],
                        'error' => 'Email registered by concurrent request',
                    ]);
                    
                    return back()
                        ->withInput($request->except('password', 'password_confirmation'))
                        ->withErrors(['email' => 'Este correo electrónico ya está registrado.']);
                }

                // ═══════════════════════════════════════════════════════════════
                // SLUG COLLISION: Retryable - log and continue
                // ═══════════════════════════════════════════════════════════════
                Log::warning('Registration slug collision, retrying', [
                    'email' => $validated['email'],
                    'attempt' => $attempt,
                    'slug_attempted' => $currentSlug,
                ]);

                // LINEAR BACKOFF: 50ms * attempt to reduce thundering herd
                usleep(50000 * $attempt);
            }

        } while ($attempt < $maxRetries);

        // ═══════════════════════════════════════════════════════════════════
        // MAX RETRIES EXHAUSTED: Graceful failure with operator alert
        // ═══════════════════════════════════════════════════════════════════
        Log::error('Registration failed: Max retries exhausted', [
            'email' => $validated['email'],
            'attempts' => $attempt,
            'last_error' => $lastException?->getMessage(),
        ]);

        return back()
            ->withInput($request->except('password', 'password_confirmation'))
            ->withErrors(['email' => 'El sistema está experimentando alta demanda. Por favor intenta de nuevo en unos segundos.']);
    }

    /**
     * Generate a URL-friendly slug from a business name.
     *
     * @param string $name
     * @return string
     */
    private function generateSlug(string $name): string
    {
        return Str::slug($name, '-');
    }

    /**
     * Ensure slug uniqueness - O(1) DB complexity.
     *
     * OPTIMIZATION: Single query fetches all potential collisions,
     * then in-memory processing calculates next available suffix.
     * Previous implementation had O(n) complexity with one SELECT per collision.
     *
     * @param string $baseSlug
     * @return string
     */
    private function ensureUniqueSlug(string $baseSlug): string
    {
        // O(1) DB complexity: Single query fetches all potential collisions
        $existingSlugs = Tenant::where('slug', $baseSlug)
            ->orWhere('slug', 'LIKE', "{$baseSlug}-%")
            ->pluck('slug')
            ->toArray();

        // Base slug available - no collision
        if (!in_array($baseSlug, $existingSlugs, true)) {
            return $baseSlug;
        }

        // In-memory suffix calculation (no additional DB queries)
        $maxSuffix = 0;
        foreach ($existingSlugs as $existingSlug) {
            if (preg_match('/^' . preg_quote($baseSlug, '/') . '-(\d+)$/', $existingSlug, $matches)) {
                $maxSuffix = max($maxSuffix, (int) $matches[1]);
            }
        }

        return "{$baseSlug}-" . ($maxSuffix + 1);
    } 
}
