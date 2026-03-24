<div class="biz-stack">
    <div class="biz-card">
        <div class="biz-stack">
            <h3 class="biz-heading">Informacion del evento</h3>

            <div class="biz-definition-grid">
                <div>
                    <p class="biz-definition-term">Evento</p>
                    <p class="biz-definition-value">{{ ucfirst($record->event) }}</p>
                </div>

                <div>
                    <p class="biz-definition-term">Entidad</p>
                    <p class="biz-definition-value">{{ class_basename($record->subject_type) }} #{{ $record->subject_id }}</p>
                </div>

                <div>
                    <p class="biz-definition-term">Fecha</p>
                    <p class="biz-definition-value">{{ $record->created_at->format('d/m/Y H:i:s') }}</p>
                </div>

                <div>
                    <p class="biz-definition-term">Ejecutado por</p>
                    <p class="biz-definition-value">{{ $record->causer?->name ?? 'Sistema' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($record->properties->has('ip'))
        <div class="biz-card-warning">
            <div class="biz-stack">
                <h3 class="biz-heading">Contexto de red</h3>

                <div class="biz-definition-grid">
                    <div>
                        <p class="biz-definition-term">Direccion IP</p>
                        <p class="biz-definition-value biz-table-cell-mono">{{ $record->properties->get('ip') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($record->properties->has('old'))
        <div class="biz-card-danger">
            <div class="biz-stack">
                <h3 class="biz-heading">Valores anteriores</h3>
                <pre class="biz-code-block">{{ json_encode($record->properties->get('old'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif

    @if ($record->properties->has('attributes'))
        <div class="biz-card-accent">
            <div class="biz-stack">
                <h3 class="biz-heading">Valores nuevos</h3>
                <pre class="biz-code-block">{{ json_encode($record->properties->get('attributes'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif

    <div class="biz-card">
        <div class="biz-stack">
            <h3 class="biz-heading">Descripcion</h3>
            <p class="biz-copy">{{ $record->description }}</p>
        </div>
    </div>
</div>
