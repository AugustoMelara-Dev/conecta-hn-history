@component('mail::message')
# Recordatorio de cita

Hola,

Te recordamos que tienes una cita programada:

**Negocio:** {{ $tenantName }}

**Servicio:** {{ $serviceName }}

**Fecha:** {{ $date }}

**Hora:** {{ $time }}

**Atendido por:** {{ $providerName }}

---

Si necesitas cancelar o reprogramar tu cita, usa el boton de abajo.

@component('mail::button', ['url' => $managementUrl])
Gestionar cita
@endcomponent

Te esperamos.

Saludos,<br>
{{ $tenantName }}
@endcomponent
