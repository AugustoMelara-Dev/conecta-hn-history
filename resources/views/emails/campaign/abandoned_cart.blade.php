<!DOCTYPE html>
<html>
<head>
    <title>Tu carrito te espera</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Hola, {{ $user->name }}!</h2>

    <p>Notamos que dejaste algunos artículos en tu carrito en <strong>{{ $tenantName }}</strong>.</p>
    <p>El total de tu carrito era de L. {{ number_format($subtotal, 2) }}.</p>

    <p>Sabemos que la vida es ocupada, así que queremos darte un pequeño incentivo para terminar tu compra. Usa el siguiente código de descuento para obtener <strong>{{ $discountText }}</strong> en tu orden:</p>

    <div style="background-color: #f4f4f4; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px;">
        <h3 style="margin: 0; color: #000; letter-spacing: 2px;">{{ $couponCode }}</h3>
        <p style="font-size: 12px; color: #666; margin-top: 5px;">*Válido por las próximas 24 horas.</p>
    </div>

    <p>
        <a href="{{ config('app.url') }}/checkout" style="display: inline-block; padding: 10px 20px; background-color: #000; color: #fff; text-decoration: none; border-radius: 5px;">
            Terminar mi compra
        </a>
    </p>

    <p style="font-size: 12px; color: #999; margin-top: 30px;">
        Si ya no deseas recibir estos correos, por favor actualiza tus preferencias en tu perfil.
    </p>
</div>
</body>
</html>
