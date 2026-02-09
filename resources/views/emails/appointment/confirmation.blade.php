<x-mail::message>
# ¡Tu cita está confirmada!

Hola {{ $appointment->customer_name }},

Tu reserva en **{{ $tenantName }}** ha sido programada con éxito.

<x-mail::panel>
**Servicio:** {{ $serviceName }}

**Fecha:** {{ $date }}

**Atendido por:** {{ $providerName }}
</x-mail::panel>

Si necesitas reprogramar, por favor contáctanos directamente.

<x-mail::button :url="$managementUrl">
Gestionar mi Cita
</x-mail::button>

Si necesitas cancelar o reprogramar, usa el botón de arriba o contáctanos.

Gracias,<br>
{{ $tenantName }}
</x-mail::message>
