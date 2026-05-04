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
                    <a href="{{ route('tenant.products.index') }}" wire:navigate aria-label="Volver al catalogo de productos" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">Configuración</h1>
                </div>
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    aria-label="Guardar cambios de configuracion del negocio"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-colors"
                >
                    <span wire:loading.remove>Guardar cambios</span>
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
                    <img src="{{ $cover->temporaryUrl() }}" alt="Vista previa de la portada" class="w-full h-full object-cover"/>
                @elseif($existingCover)
                    <img src="{{ Storage::url($existingCover) }}" alt="Portada actual del negocio" class="w-full h-full object-cover"/>
                @endif

                {{-- Cover Upload Button --}}
                <label for="settings-cover-upload" class="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 backdrop-blur text-gray-700 text-sm font-medium rounded-lg cursor-pointer hover:bg-white transition-colors">
                    <input id="settings-cover-upload" type="file" wire:model="cover" accept="image/*" aria-describedby="settings-cover-help" class="sr-only"/>
                    Cambiar portada
                </label>

                @if($existingCover || $cover)
                    <button
                        wire:click="removeCover"
                        aria-label="Eliminar portada del negocio"
                        class="absolute bottom-3 right-36 p-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600"
                        title="Eliminar portada"
                    >
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Logo Avatar --}}
            <div class="relative -mt-12 ml-6">
                <div class="w-24 h-24 rounded-xl bg-white shadow-lg border-4 border-white overflow-hidden">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Vista previa del logo" class="w-full h-full object-cover"/>
                    @elseif($existingLogo)
                        <img src="{{ Storage::url($existingLogo) }}" alt="Logo actual del negocio" class="w-full h-full object-cover"/>
                    @else
                        <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-2xl font-bold">
                            {{ substr($name, 0, 2) }}
                        </div>
                    @endif
                </div>

                <label for="settings-logo-upload" class="absolute -bottom-1 -right-1 p-1.5 bg-blue-600 text-white rounded-full cursor-pointer hover:bg-blue-700">
                    <span class="sr-only">Cambiar logo del negocio</span>
                    <input id="settings-logo-upload" type="file" wire:model="logo" accept="image/*" aria-describedby="settings-logo-help" class="sr-only"/>
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </label>
            </div>

            <p id="settings-logo-help" class="ml-6 mt-2 text-xs text-gray-500">Usa una imagen clara para identificar tu negocio.</p>
            <p id="settings-cover-help" class="ml-6 mt-1 text-xs text-gray-500">La portada se mostrará como imagen principal de tu negocio.</p>

            {{-- Upload Errors --}}
            @error('logo') <p class="ml-6 mt-2 text-sm text-red-500">{{ $message }}</p> @enderror
            @error('cover') <p class="ml-6 mt-2 text-sm text-red-500">{{ $message }}</p> @enderror

            {{-- Basic Info --}}
            <div class="p-6 pt-4 space-y-4">
                <div>
                    <label for="settings-name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del negocio *</label>
                    <input
                        id="settings-name"
                        wire:model="name"
                        type="text"
                        aria-describedby="settings-name-help @error('name') settings-name-error @enderror"
                        aria-invalid="@error('name') true @else false @enderror"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-300 @enderror"
                    />
                    <p id="settings-name-help" class="mt-1 text-sm text-gray-500">Este nombre se mostrará a tus clientes en tu perfil público.</p>
                    @error('name') <p id="settings-name-error" class="mt-1 text-sm text-red-500" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="settings-description" class="block text-sm font-medium text-gray-700 mb-1">Descripción corta</label>
                    <textarea
                        id="settings-description"
                        wire:model="description"
                        rows="2"
                        aria-describedby="settings-description-help"
                        placeholder="Una frase que define tu negocio..."
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    ></textarea>
                    <p id="settings-description-help" class="mt-1 text-sm text-gray-500">Resume lo que haces en una frase clara y verificable.</p>
                </div>

                <div>
                    <label for="settings-about" class="block text-sm font-medium text-gray-700 mb-1">Nuestra historia</label>
                    <textarea
                        id="settings-about"
                        wire:model="about"
                        rows="4"
                        aria-describedby="settings-about-help"
                        placeholder="Cuéntale a tus clientes quiénes son, cómo empezaron..."
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    ></textarea>
                    <p id="settings-about-help" class="mt-1 text-sm text-gray-500">Cuenta quiénes son y qué puede esperar el cliente, sin promesas exageradas.</p>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 2: Contact & Social --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Contacto y redes</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Phone --}}
                <div>
                    <label for="settings-phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">☎</span>
                        <input
                            id="settings-phone"
                            wire:model="phone"
                            type="text"
                            aria-describedby="settings-phone-help"
                            placeholder="+504 9999-9999"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-phone-help" class="sr-only">Número de contacto para llamadas de clientes.</p>
                    </div>
                </div>

                {{-- WhatsApp --}}
                <div>
                    <label for="settings-whatsapp" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-green-500" aria-hidden="true">WA</span>
                        <input
                            id="settings-whatsapp"
                            wire:model="whatsapp_number"
                            type="text"
                            aria-describedby="settings-whatsapp-help"
                            placeholder="+504 9999-9999"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-whatsapp-help" class="sr-only">Número que usarán los clientes para escribirte por WhatsApp.</p>
                    </div>
                </div>

                {{-- Address --}}
                <div class="md:col-span-2">
                    <label for="settings-address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">📍</span>
                        <input
                            id="settings-address"
                            wire:model="address"
                            type="text"
                            aria-describedby="settings-address-help"
                            placeholder="Ej: Colonia Kennedy, Tegucigalpa"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-address-help" class="sr-only">Ubicación o zona de referencia que quieres mostrar públicamente.</p>
                    </div>
                </div>

                {{-- Instagram --}}
                <div>
                    <label for="settings-instagram-url" class="block text-sm font-medium text-gray-700 mb-1">Instagram</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-pink-500" aria-hidden="true">IG</span>
                        <input
                            id="settings-instagram-url"
                            wire:model="instagram_url"
                            type="url"
                            aria-describedby="settings-instagram-help"
                            placeholder="https://instagram.com/tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-instagram-help" class="sr-only">Enlace completo a tu perfil de Instagram.</p>
                    </div>
                </div>

                {{-- Facebook --}}
                <div>
                    <label for="settings-facebook-url" class="block text-sm font-medium text-gray-700 mb-1">Facebook</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-blue-600" aria-hidden="true">FB</span>
                        <input
                            id="settings-facebook-url"
                            wire:model="facebook_url"
                            type="url"
                            aria-describedby="settings-facebook-help"
                            placeholder="https://facebook.com/tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-facebook-help" class="sr-only">Enlace completo a tu página de Facebook.</p>
                    </div>
                </div>

                {{-- TikTok --}}
                <div>
                    <label for="settings-tiktok-url" class="block text-sm font-medium text-gray-700 mb-1">TikTok</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2" aria-hidden="true">TT</span>
                        <input
                            id="settings-tiktok-url"
                            wire:model="tiktok_url"
                            type="url"
                            aria-describedby="settings-tiktok-help"
                            placeholder="https://tiktok.com/@tunegocio"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-tiktok-help" class="sr-only">Enlace completo a tu perfil de TikTok.</p>
                    </div>
                </div>

                {{-- Website --}}
                <div>
                    <label for="settings-website-url" class="block text-sm font-medium text-gray-700 mb-1">Sitio web</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true">URL</span>
                        <input
                            id="settings-website-url"
                            wire:model="website_url"
                            type="url"
                            aria-describedby="settings-website-help"
                            placeholder="https://tunegocio.com"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <p id="settings-website-help" class="sr-only">Enlace completo a tu sitio web, si tienes uno.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- SECTION 3: Schedule Matrix --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Horarios de atención</h2>

            <div class="space-y-3">
                @foreach($this->dayLabels as $day => $label)
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl {{ !($schedule[$day]['isOpen'] ?? false) ? 'opacity-50' : '' }}">
                        {{-- Day Label --}}
                        <div class="w-24 font-medium text-gray-900">{{ $label }}</div>

                        {{-- Toggle --}}
                        <button
                            type="button"
                            wire:click="$set('schedule.{{ $day }}.isOpen', {{ ($schedule[$day]['isOpen'] ?? false) ? 'false' : 'true' }})"
                            role="switch"
                            aria-checked="{{ ($schedule[$day]['isOpen'] ?? false) ? 'true' : 'false' }}"
                            aria-label="{{ ($schedule[$day]['isOpen'] ?? false) ? 'Marcar '.$label.' como cerrado' : 'Marcar '.$label.' como abierto' }}"
                            class="relative w-12 h-7 rounded-full transition-colors {{ ($schedule[$day]['isOpen'] ?? false) ? 'bg-emerald-500' : 'bg-gray-300' }}"
                        >
                            <span class="absolute top-1 {{ ($schedule[$day]['isOpen'] ?? false) ? 'right-1' : 'left-1' }} w-5 h-5 bg-white rounded-full shadow transition-transform"></span>
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
                                aria-label="Hora de apertura para {{ $label }}"
                                {{ !($schedule[$day]['isOpen'] ?? false) ? 'disabled' : '' }}
                                class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                            />
                            <span class="text-gray-400">—</span>
                            <input
                                type="time"
                                wire:model="schedule.{{ $day }}.close"
                                aria-label="Hora de cierre para {{ $label }}"
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
                aria-label="Guardar cambios de configuracion del negocio"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg"
            >
                <span wire:loading.remove>Guardar cambios</span>
                <span wire:loading>Guardando...</span>
            </button>
        </div>
    </main>
</div>
