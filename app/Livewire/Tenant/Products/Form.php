<?php

declare(strict_types=1);

namespace App\Livewire\Tenant\Products;

use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Products Form - Create & Edit with Tiered Logic (FREE vs PRO)
 *
 * Features:
 * - Single component for create/edit (DRY)
 * - Image upload with preview
 * - Plan limit enforcement on backend
 * - Ownership verification for edits
 * - Inline category creation
 * - PRO-only fields sanitization
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

    // ─────────────────────────────────────────────────────────────────────────
    // Basic Fields (All Plans)
    // ─────────────────────────────────────────────────────────────────────────
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = '';

    #[Validate('required|numeric|min:0|max:999999.99')]
    public float $price = 0;

    #[Validate('boolean')]
    public bool $is_visible = true;

    // ─────────────────────────────────────────────────────────────────────────
    // Advanced Fields (PRO Features - Sanitized for FREE)
    // ─────────────────────────────────────────────────────────────────────────
    public ?int $menu_section_id = null;

    public string $stock_status = 'in_stock';

    public bool $is_featured = false;

    public bool $is_new = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Inline Category Creation
    // ─────────────────────────────────────────────────────────────────────────
    public string $newCategoryName = '';

    public bool $showCategoryModal = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Image Handling
    // ─────────────────────────────────────────────────────────────────────────
    #[Validate('nullable|image|max:1024')] // Max 1MB
    public $image = null;

    public ?string $existingImage = null;

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Centralized PRO status check
     */
    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    /**
     * Available menu sections for dropdown
     */
    #[Computed]
    public function sections(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->tenant->menuSections()->active()->ordered()->get();
    }

    /**
     * Stock status options
     */
    #[Computed]
    public function stockOptions(): array
    {
        return [
            'in_stock' => 'Disponible',
            'out_of_stock' => 'Agotado por hoy',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

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

            // Load existing data - Basic
            $this->name = $product->name ?? '';
            $this->description = $product->description ?? '';
            $this->price = (float) $product->price;
            $this->is_visible = (bool) $product->is_visible;
            $this->existingImage = $product->image_path;

            // Load existing data - Advanced (PRO fields)
            $this->menu_section_id = $product->menu_section_id;
            $this->stock_status = $product->stock_status ?? 'in_stock';
            $this->is_featured = (bool) $product->is_featured;
            $this->is_new = (bool) $product->is_new;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create new category inline (PRO only)
     */
    public function createCategory(): void
    {
        // Guard: Only PRO users can create categories
        if (! $this->isPro) {
            session()->flash('error', 'Las categorías son una función PRO.');

            return;
        }

        $this->validate([
            'newCategoryName' => 'required|string|max:100',
        ]);

        $section = MenuSection::create([
            'tenant_id' => $this->tenant->id,
            'name' => trim($this->newCategoryName),
            'is_active' => true,
            'sort_order' => $this->sections->count(),
        ]);

        // Auto-select the new section
        $this->menu_section_id = $section->id;

        // Reset and close
        $this->newCategoryName = '';
        $this->showCategoryModal = false;

        session()->flash('toast', '✅ Categoría creada');
    }

    /**
     * Save product (create or update) with sanitization
     */
    public function save()
    {
        $this->validate();

        $isPro = $this->isPro;

        // ─────────────────────────────────────────────────────────────────────
        // GUARD CLAUSE: Plan limit (only for new products)
        // ─────────────────────────────────────────────────────────────────────
        if (! $this->isEditing) {
            // Use Model Helper to avoid Architecture Violation
            $currentCount = Product::countAllForTenant($this->tenant->id);

            $limit = $isPro ? self::LIMIT_PRO : self::LIMIT_FREE;

            if ($currentCount >= $limit) {
                throw ValidationException::withMessages([
                    'name' => "Has alcanzado el límite de {$limit} productos. Mejora tu plan para añadir más.",
                ]);
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // SANITIZATION: Force FREE users to default values (Security Guard)
        // ─────────────────────────────────────────────────────────────────────
        if (! $isPro) {
            $this->is_featured = false;
            $this->is_new = false;
            $this->menu_section_id = null;
            // stock_status can remain as basic feature
        }

        // ─────────────────────────────────────────────────────────────────────
        // Handle image upload
        // ─────────────────────────────────────────────────────────────────────
        $imagePath = $this->existingImage;

        if ($this->image) {
            // Delete old image if editing (Garbage Collection)
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
            'slug' => $this->isEditing ? $this->product->slug : Str::slug($this->name).'-'.Str::random(5),
            'description' => $this->description,
            'price' => $this->price,
            'is_visible' => $this->is_visible,
            'image_path' => $imagePath,
            // Advanced fields (sanitized above for FREE)
            'menu_section_id' => $this->menu_section_id,
            'stock_status' => $this->stock_status,
            'is_featured' => $this->is_featured,
            'is_new' => $this->is_new,
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
