<!DOCTYPE html>
<html>
<head>
<title>Espacio Liberado</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
<h2>¡Hola, {{ $customerName }}!</h2>

    <p>Te uniste a nuestra lista de espera para el servicio de <strong>{{ $serviceName }}</strong> el <strong>{{ $date }}</strong>.</p>

    <p>¡Tenemos buenas noticias! Se acaba de liberar un espacio en la agenda de <strong>{{ $tenantName }}</strong> para ese día.</p>

    <p>Los espacios se asignan por orden de llegada, así que te recomendamos reservar lo antes posible utilizando el siguiente enlace:</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #000; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Reservar Ahora
        </a>
    </p>

    <p style="font-size: 12px; color: #999; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
        Este es un aviso automático de disponibilidad. Si ya no estás interesado o ya reservaste, puedes ignorar este correo. Gracias por preferir a {{ $tenantName }}.
    </p>
</div>
</body>
</html>