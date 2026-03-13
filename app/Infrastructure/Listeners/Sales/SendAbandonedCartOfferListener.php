<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Application\Shared\DTOs\Mail\UserMailData;
use App\Domain\Sales\Events\CartAbandoned;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Mail\AbandonedCartOfferMail;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class SendAbandonedCartOfferListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function middleware(): array
    {
        return [app(TenantQueueMiddleware::class)];
    }

    public function handle(CartAbandoned $event): void
    {
        if ($event->userId === null) {
            Log::info('SendAbandonedCartOfferListener: Skipped anonymous cart', [
                'session_id' => $event->sessionId,
            ]);

            return;
        }

        $tenant = Tenant::query()->where('id', $event->tenantId)->first();
        $user = User::query()
            ->where('id', $event->userId)
            ->where('tenant_id', $event->tenantId)
            ->first();

        if (! $user || ! $tenant) {
            Log::warning('SendAbandonedCartOfferListener: Missing User or Tenant', [
                'user_id' => $event->userId,
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $couponCode = 'COMEBACK-'.strtoupper(Str::random(6));

        try {
            $coupon = new Coupon();
            $coupon->tenant_id = (string) $event->tenantId;
            $coupon->code = $couponCode;
            $coupon->name = 'Abandoned Cart Recovery';
            $coupon->description = 'Recovery offer generated for abandoned checkout session '.$event->sessionId;
            $coupon->type = Coupon::TYPE_PERCENT;
            $coupon->value = 10.00;
            $coupon->usage_limit = 1;
            $coupon->starts_at = now();
            $coupon->expires_at = now()->addHours(24);
            $coupon->is_active = true;
            $coupon->save();
        } catch (\Throwable $e) {
            Log::error('SendAbandonedCartOfferListener: Failed to generate coupon', [
                'error' => $e->getMessage(),
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $userDto = new UserMailData(
            id: is_numeric((string) $user->id) ? (int) $user->id : 0,
            name: (string) $user->name,
            email: (string) $user->email,
            tenantId: is_numeric((string) $event->tenantId) ? (int) $event->tenantId : null,
        );

        try {
            Mail::to($user->email)->send(new AbandonedCartOfferMail(
                user: $userDto,
                tenantName: (string) ($tenant->name ?? 'Nuestra Tienda'),
                couponCode: $couponCode,
                discountText: '10% de descuento',
                subtotal: $event->subtotal,
            ));

            Log::info('SendAbandonedCartOfferListener: Offer sent successfully', [
                'user_id' => $user->id,
                'coupon' => $couponCode,
            ]);
        } catch (\Throwable $e) {
            Log::error('SendAbandonedCartOfferListener: Mail delivery failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }
    }
}
