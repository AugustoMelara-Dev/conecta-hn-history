{{--
    Products Form - Create & Edit
    
    Features:
    - Two-column layout (desktop)
    - Image upload with preview
    - Real-time validation
    - Loading state on submit
--}}

<div class="min-h-screen bg-gray-50">
    {{-- Header --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="{{ route('tenant.products.index') }}" wire:navigate class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">
                        {{ $isEditing ? 'Editar Producto' : 'Nuevo Producto' }}
                    </h1>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- LEFT COLUMN: Form Fields --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Basic Info Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="font-semibold text-gray-900 mb-4">Información del Producto</h2>
                        
                        {{-- Name --}}
                        <div class="mb-5">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre del producto *
                            </label>
                            <input
                                wire:model="name"
                                type="text"
                                id="name"
                                placeholder="Ej: Hamburguesa Clásica"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                    @error('name') border-red-300 bg-red-50 @enderror"
                            />
                            @error('name')
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Price --}}
                        <div class="mb-5">
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                Precio (Lempiras) *
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">L.</span>
                                <input
                                    wire:model="price"
                                    type="number"
                                    id="price"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                        @error('price') border-red-300 bg-red-50 @enderror"
                                />
                            </div>
                            @error('price')
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="mb-5">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Descripción
                            </label>
                            <textarea
                                wire:model="description"
                                id="description"
                                rows="4"
                                placeholder="Describe tu producto..."
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none
                                    @error('description') border-red-300 bg-red-50 @enderror"
                            ></textarea>
                            @error('description')
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Visibility Toggle --}}
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-medium text-gray-900">Visibilidad</h3>
                                <p class="text-sm text-gray-500">
                                    {{ $is_visible ? 'El producto es visible para tus clientes' : 'El producto está oculto' }}
                                </p>
                            </div>
                            <button 
                                type="button"
                                wire:click="$toggle('is_visible')"
                                class="relative w-14 h-8 rounded-full transition-colors {{ $is_visible ? 'bg-emerald-500' : 'bg-gray-300' }}"
                            >
                                <span class="absolute top-1 {{ $is_visible ? 'right-1' : 'left-1' }} w-6 h-6 bg-white rounded-full shadow transition-all"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- RIGHT COLUMN: Image Upload --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="font-semibold text-gray-900 mb-4">Imagen</h2>

                        {{-- Image Preview --}}
                        <div class="mb-4">
                            @if($image)
                                {{-- New temporary image --}}
                                <div class="relative aspect-square bg-gray-100 rounded-xl overflow-hidden">
                                    <img 
                                        src="{{ $image->temporaryUrl() }}" 
                                        alt="Vista previa"
                                        class="w-full h-full object-cover"
                                    />
                                    <button 
                                        type="button"
                                        wire:click="$set('image', null)"
                                        class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-full hover:bg-red-600"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @elseif($existingImage)
                                {{-- Existing image --}}
                                <div class="relative aspect-square bg-gray-100 rounded-xl overflow-hidden">
                                    <img 
                                        src="{{ Storage::url($existingImage) }}" 
                                        alt="Imagen actual"
                                        class="w-full h-full object-cover"
                                    />
                                    <button 
                                        type="button"
                                        wire:click="removeImage"
                                        class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-full hover:bg-red-600"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @else
                                {{-- Upload placeholder --}}
                                <label class="block aspect-square bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:bg-gray-100 hover:border-gray-400 transition-colors">
                                    <input 
                                        type="file" 
                                        wire:model="image" 
                                        accept="image/*"
                                        class="hidden"
                                    />
                                    <div class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium">Subir imagen</span>
                                        <span class="text-xs mt-1">Máx. 1MB</span>
                                    </div>
                                </label>
                            @endif
                        </div>

                        {{-- Loading indicator for image upload --}}
                        <div wire:loading wire:target="image" class="text-center py-4">
                            <x-spinner size="md" class="mx-auto text-blue-500" />
                            <p class="text-sm text-gray-500 mt-2">Subiendo imagen...</p>
                        </div>

                        @error('image')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        {{-- Replace image button --}}
                        @if($image || $existingImage)
                            <label class="block mt-4">
                                <input 
                                    type="file" 
                                    wire:model="image" 
                                    accept="image/*"
                                    class="hidden"
                                />
                                <span class="inline-flex items-center justify-center w-full gap-2 px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-xl cursor-pointer hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    Cambiar imagen
                                </span>
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- SUBMIT BUTTON --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="flex items-center justify-end gap-4 pt-4">
                <a 
                    href="{{ route('tenant.products.index') }}" 
                    wire:navigate
                    class="px-6 py-3 text-gray-700 font-medium hover:text-gray-900"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait"
                    class="inline-flex items-center justify-center gap-2 px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                >
                    <span wire:loading.remove>
                        {{ $isEditing ? 'Guardar Cambios' : 'Crear Producto' }}
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <x-spinner size="sm" />
                        Guardando...
                    </span>
                </button>
            </div>
        </form>
    </main>
</div>
