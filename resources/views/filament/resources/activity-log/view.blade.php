<div class="space-y-4">
    {{-- Informacion del Evento --}}
    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="font-bold text-lg mb-2">Informacion del Evento</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-gray-500">Evento:</span>
                <span class="font-medium ml-2">{{ ucfirst($record->event) }}</span>
            </div>
            <div>
                <span class="text-gray-500">Entidad:</span>
                <span class="font-medium ml-2">{{ class_basename($record->subject_type) }} #{{ $record->subject_id }}</span>
            </div>
            <div>
                <span class="text-gray-500">Fecha:</span>
                <span class="font-medium ml-2">{{ $record->created_at->format('d/m/Y H:i:s') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Ejecutado por:</span>
                <span class="font-medium ml-2">{{ $record->causer?->name ?? 'Sistema' }}</span>
            </div>
        </div>
    </div>

    {{-- Direccion IP --}}
    @if($record->properties->has('ip'))
    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
        <h3 class="font-bold text-lg mb-2">Contexto de Red</h3>
        <div>
            <span class="text-gray-500">Direccion IP:</span>
            <span class="font-mono ml-2 bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">{{ $record->properties->get('ip') }}</span>
        </div>
    </div>
    @endif

    {{-- Valores Anteriores (old_attributes) --}}
    @if($record->properties->has('old'))
    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
        <h3 class="font-bold text-lg mb-2 text-red-700 dark:text-red-400">Valores Anteriores</h3>
        <pre class="text-sm overflow-x-auto bg-white dark:bg-gray-900 p-3 rounded">{{ json_encode($record->properties->get('old'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif

    {{-- Valores Nuevos (new_attributes) --}}
    @if($record->properties->has('attributes'))
    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
        <h3 class="font-bold text-lg mb-2 text-green-700 dark:text-green-400">Valores Nuevos</h3>
        <pre class="text-sm overflow-x-auto bg-white dark:bg-gray-900 p-3 rounded">{{ json_encode($record->properties->get('attributes'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif

    {{-- Descripcion Completa --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h3 class="font-bold text-lg mb-2">Descripcion</h3>
        <p>{{ $record->description }}</p>
    </div>
</div>
