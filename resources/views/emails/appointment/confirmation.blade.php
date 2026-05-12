<x-mail::message>
# Tu cita está confirmada

Hola {{ $appointment->customer_name }},

Tu reserva en **{{ $tenantName }}** ha sido programada con exito.

<x-mail::panel>
**Servicio:** {{ $serviceName }}

**Fecha:** {{ $date }}

**Atendido por:** {{ $providerName }}
</x-mail::panel>

Si necesitas cancelar o reprogramar, usa el boton de abajo o contactanos directamente.

<x-mail::button :url="$managementUrl">
Gestionar mi Cita
</x-mail::button>

Gracias,<br>
{{ $tenantName }}
</x-mail::message>
