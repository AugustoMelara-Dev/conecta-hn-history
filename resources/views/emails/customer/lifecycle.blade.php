<x-mail::message>
# Hola {{ $customer_name ?: 'cliente' }},

{{ $body }}

@if(! empty($cta_label) && ! empty($cta_url))
<x-mail::button :url="$cta_url">
{{ $cta_label }}
</x-mail::button>
@endif

Gracias,<br>
{{ $tenant_name ?? 'Vito Business OS' }}
</x-mail::message>
