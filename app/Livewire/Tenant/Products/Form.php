<?php

namespace App\Livewire\Tenant\Products;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Products Form - Create & Edit (DRY)
 *
 * Features:
 * - Single component for create/edit
 * - Image upload with preview
 * - Plan limit enforcement on backend
 * - Ownership verification for edits
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use WithFileUploads;

    // ═══════════════════════════════════════════════════════════════════════
    // PLAN LIMITS
    // ═══════════════════════════════════════════════════════════════════════

    public const LIMIT_FREE = 10;

    public const LIMIT_PRO = 100;

    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════

    public Tenant $tenant;

    public ?Product $product = null;

    public bool $isEditing = false;

    // Form Fields
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = '';

    #[Validate('required|numeric|min:0|max:999999.99')]
    public float $price = 0;

    #[Validate('boolean')]
    public bool $is_visible = true;

    // Image handling
    #[Validate('nullable|image|max:1024')] // Max 1MB
    public $image = null;

    public ?string $existingImage = null;

    /**
     * Mount for create or edit
     */
    public function mount(?Product $product = null): void
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            abort(403, 'No tienes un negocio asociado.');
        }

        $this->tenant = $user->tenant;

        // ─────────────────────────────────────────────────────────────────────
        // EDIT MODE
        // ─────────────────────────────────────────────────────────────────────
        if ($product && $product->exists) {
            // Security: Verify ownership
            if ($product->tenant_id !== $this->tenant->id) {
                abort(403, 'No tienes permiso para editar este producto.');
            }

            $this->product = $product;
            $this->isEditing = true;

            // Load existing data
            $this->name = $product->name ?? '';
            $this->description = $product->description ?? '';
            $this->price = (float) $product->price;
            $this->is_visible = (bool) $product->is_visible;
            $this->existingImage = $product->image_path;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Save product (create or update)
     */
    public function save()
    {
        $this->validate();

        // ─────────────────────────────────────────────────────────────────────
        // GUARD CLAUSE: Plan limit (only for new products)
        // ─────────────────────────────────────────────────────────────────────
        if (! $this->isEditing) {
            $currentCount = Product::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->id)
                ->count();

            $limit = $this->tenant->is_pro ? self::LIMIT_PRO : self::LIMIT_FREE;

            if ($currentCount >= $limit) {
                throw ValidationException::withMessages([
                    'name' => "Has alcanzado el límite de {$limit} productos. Mejora tu plan para añadir más.",
                ]);
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // Handle image upload
        // ─────────────────────────────────────────────────────────────────────
        $imagePath = $this->existingImage;

        if ($this->image) {
            // Delete old image if editing
            if ($this->isEditing && $this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }

            // Store new image in tenant-specific folder
            $folder = "tenants/{$this->tenant->id}/products";
            $imagePath = $this->image->store($folder, 'public');
        }

        // ─────────────────────────────────────────────────────────────────────
        // Persist product
        // ─────────────────────────────────────────────────────────────────────
        $data = [
            'tenant_id' => $this->tenant->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name).'-'.Str::random(5),
            'description' => $this->description,
            'price' => $this->price,
            'is_visible' => $this->is_visible,
            'image_path' => $imagePath,
        ];

        if ($this->isEditing) {
            $this->product->update($data);
            $message = '✅ Producto actualizado correctamente';
        } else {
            Product::create($data);
            $message = '🎉 Producto creado correctamente';
        }

        session()->flash('toast', $message);

        return redirect()->route('tenant.products.index');
    }

    /**
     * Remove current image
     */
    public function removeImage(): void
    {
        if ($this->existingImage) {
            Storage::disk('public')->delete($this->existingImage);
            $this->existingImage = null;

            if ($this->isEditing && $this->product) {
                $this->product->update(['image_path' => null]);
            }
        }

        $this->image = null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        $title = $this->isEditing
            ? "Editar: {$this->product->name}"
            : 'Nuevo Producto';

        return view('livewire.tenant.products.form')
            ->title($title.' | '.$this->tenant->name);
    }
}
