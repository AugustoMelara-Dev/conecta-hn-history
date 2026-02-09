@component('mail::message')
# Recordatorio de Cita

Hola,

Te recordamos que tienes una cita programada:

**Negocio:** {{ $tenantName }}

**Servicio:** {{ $serviceName }}

**Fecha:** {{ $date }}

**Hora:** {{ $time }}

**Atendido por:** {{ $providerName }}

---

Si necesitas cancelar o reprogramar tu cita, haz clic en el siguiente botón:

@component('mail::button', ['url' => $managementUrl])
Gestionar Cita
@endcomponent

¡Te esperamos!

Saludos,<br>
{{ $tenantName }}
@endcomponent
