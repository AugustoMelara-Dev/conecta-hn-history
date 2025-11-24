<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:buyer,seller',
            'business_name' => 'required_if:role,seller|string|max:255|unique:tenants,name|nullable',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            // Auto-create tenant for sellers
            if ($request->role === 'seller' && $request->business_name) {
                $defaultCategory = \App\Models\Category::where('slug', 'servicios')->first() ?? \App\Models\Category::first();

                $user->tenants()->create([
                    'name' => $request->business_name,
                    'slug' => Str::slug($request->business_name).'-'.Str::random(4),
                    'status' => 'approved', // Auto-approve for instant access
                    'category_id' => $defaultCategory ? $defaultCategory->id : 1,
                    'city_id' => 1, // Default city
                    'is_pro' => false,
                    'product_limit' => 10,
                ]);
            }

            DB::commit();

            event(new Registered($user));

            Auth::login($user);

            // Role-based redirect (using absolute paths for clarity)
            if ($user->role === 'seller') {
                $tenant = $user->tenants->first();
                if ($tenant) {
                    return redirect("/app/{$tenant->slug}");
                }

                return redirect('/admin'); // Fallback if no tenant found (shouldn't happen due to transaction)
            }

            return redirect('/directorio');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
