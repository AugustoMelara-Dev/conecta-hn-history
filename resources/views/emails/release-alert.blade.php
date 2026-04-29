<p>Una feature protegida por rollout fallo en produccion.</p>

<ul>
    <li><strong>Feature:</strong> {{ $context['feature_key'] ?? 'unknown' }}</li>
    <li><strong>Trace:</strong> {{ $context['trace_id'] ?? 'n/a' }}</li>
    <li><strong>Tenant:</strong> {{ $context['tenant_slug'] ?? $context['tenant_id'] ?? 'n/a' }}</li>
    <li><strong>Usuario:</strong> {{ $context['user_id'] ?? 'n/a' }}</li>
    <li><strong>Ruta:</strong> {{ $context['request_method'] ?? 'GET' }} {{ $context['request_path'] ?? '/' }}</li>
    <li><strong>Error:</strong> {{ $context['exception_class'] ?? 'Throwable' }} - {{ $context['exception_message'] ?? 'Sin mensaje' }}</li>
</ul>

<p>Revisa Sentry/logs usando el trace id antes de activar la feature para mas tenants.</p>
