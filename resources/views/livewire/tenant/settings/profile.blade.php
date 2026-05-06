{{--
    Settings Profile - Branding & Configuration Center
    
    Features:
    - Logo & Cover upload with preview
    - Schedule matrix with toggles
    - Social media links with prefixed icons
    - Meta Business Suite inspired design
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('tenant.products.index') }}" wire:navigate class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">⚙️ Configuración</h1>
                </div>
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                >
                    <span wire:loading.remove>Guardar Cambios</span>
                    <span wire:loading class="flex items-center gap-2">
                        <x-spinner size="sm" /> Guardando...
                    </span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1: Visual Branding --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Cover Image --}}
            <div class="relative h-40 bg-gradient-to-r from-blue-500 to-indigo-600">
                @if($cover)
                    <img src="{{ $cover->temporaryUrl() }}" alt="Cover Preview" class="w-full h-full object-cover"/>
                @elseif($existingCover)
                    <img src="{{ Storage::url($existingCover) }}" alt="Cover" class="w-full h-full object-cover"/>
                @endif

                {{-- Cover Upload Button --}}
                <label class="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 backdrop-blur text-gray-700 text-sm font-medium rounded-lg cursor-pointer hover:bg-white transition-colors">
                    <input type="file" wire:model="cover" accept="image/*" class="hidden"/>
                    📷 Cambiar Portada
                </label>

                @if($existingCover || $cover)
                    <button
                        wire:click="removeCover"
                        class="absolute bottom-3 right-36 p-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600"
                        title="Eliminar portada"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Logo Avatar --}}
            <div class="relative -mt-12 ml-6">
                <div class="w-24 h-24 rounded-xl bg-white shadow-lg border-4 border-white overflow-hidden">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Logo Preview" class="w-full h-full object-cover"/>
                    @elseif($existingLogo)
                        <img src="{{ Storage::url($existingLogo) }}" alt="Logo" class="w-full h-full object-cover"/>
                    @else
                        <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-2xl font-bold">
                            {{ substr($name, 0, 2) }}
                        </div>
                    @endif
                </div>

                <label class="absolute -bottom-1 -right-1 p-1.5 bg-blue-600 text-white rounded-full cursor-pointer hover:bg-blue-700">
                    <input type="file" wire:model="logo" accept="image/*" class="hidden"/>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </label>
            </div>

            {{-- Upload Errors --}}
            @error('logo') <p class="ml-6 mt-2 text-sm text-red-500">{{ $message }}</p> @enderror
            @error('cover') <p class="ml-6 mt-2 text-sm text-red-500">{{ $message }}</p> @enderror

            {{-- Basic Info --}}
            <div class="p-6 pt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Negocio *</label>
                    <input
                        wire:model="name"
                        type="text"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-300 @enderror"
                    />
                    @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción Corta</label>
                    <textarea
                        wire:model="description"
                        rows="2"
                        placeholder="Una frase que define tu negocio..."
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nuestra Historia</label>
                    <textarea
                        wire:model="about"
                        rows="4"
                        placeholder="Cuéntale a tus clientes quiénes son, cómo empezaron..."
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    ></textarea>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 2: Contact & Social --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">📞 Contacto y Redes</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Phone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">📱</span>
                        <input
                            wire:model="phone"
                            type="text"
                            placeholder="+504 9999-9999"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- WhatsApp --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-green-500">💬</span>
                        <input
                            wire:model="whatsapp_number"
                            type="text"
                            placeholder="+504 9999-9999"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- Address --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">📍</span>
                        <input
                            wire:model="address"
                            type="text"
                            placeholder="Ej: Colonia Kennedy, Tegucigalpa"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- Instagram --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-pink-500">📸</span>
                        <input
                            wire:model="instagram_url"
                            type="url"
                            placeholder="https://instagram.com/tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- Facebook --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Facebook</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-blue-600">👍</span>
                        <input
                            wire:model="facebook_url"
                            type="url"
                            placeholder="https://facebook.com/tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- TikTok --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TikTok</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2">🎵</span>
                        <input
                            wire:model="tiktok_url"
                            type="url"
                            placeholder="https://tiktok.com/@tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- Website --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sitio Web</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🌐</span>
                        <input
                            wire:model="website_url"
                            type="url"
                            placeholder="https://tunegocio.com"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 3: Schedule Matrix --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">🕐 Horarios de Atención</h2>

            <div class="space-y-3">
                @foreach($this->dayLabels as $day => $label)
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl {{ !($schedule[$day]['isOpen'] ?? false) ? 'opacity-50' : '' }}">
                        {{-- Day Label --}}
                        <div class="w-24 font-medium text-gray-900">{{ $label }}</div>

                        {{-- Toggle --}}
                        <button
                            type="button"
                            wire:click="$set('schedule.{{ $day }}.isOpen', {{ ($schedule[$day]['isOpen'] ?? false) ? 'false' : 'true' }})"
                            class="relative w-12 h-7 rounded-full transition-colors {{ ($schedule[$day]['isOpen'] ?? false) ? 'bg-emerald-500' : 'bg-gray-300' }}"
                        >
                            <span class="absolute top-1 {{ ($schedule[$day]['isOpen'] ?? false) ? 'right-1' : 'left-1' }} w-5 h-5 bg-white rounded-full shadow transition-all"></span>
                        </button>

                        {{-- Status Text --}}
                        <span class="text-sm {{ ($schedule[$day]['isOpen'] ?? false) ? 'text-emerald-600' : 'text-gray-500' }}">
                            {{ ($schedule[$day]['isOpen'] ?? false) ? 'Abierto' : 'Cerrado' }}
                        </span>

                        {{-- Time Inputs --}}
                        <div class="flex items-center gap-2 ml-auto">
                            <input
                                type="time"
                                wire:model="schedule.{{ $day }}.open"
                                {{ !($schedule[$day]['isOpen'] ?? false) ? 'disabled' : '' }}
                                class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                            />
                            <span class="text-gray-400">—</span>
                            <input
                                type="time"
                                wire:model="schedule.{{ $day }}.close"
                                {{ !($schedule[$day]['isOpen'] ?? false) ? 'disabled' : '' }}
                                class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                            />
                        </div>
                    </div>

                    @error("schedule.{$day}")
                        <p class="text-sm text-red-500 ml-3">{{ $message }}</p>
                    @enderror
                @endforeach
            </div>
        </div>

        {{-- Mobile Save Button --}}
        <div class="lg:hidden">
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg"
            >
                <span wire:loading.remove>Guardar Cambios</span>
                <span wire:loading>Guardando...</span>
            </button>
        </div>
    </main>
</div>
