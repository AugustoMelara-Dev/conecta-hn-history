<?php

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Mail\ReleaseAlertMail;
use App\Models\Tenant;
use App\Settings\BaseSettings;
use App\Support\Release\ReleaseAlertNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

final class ReleaseAlertNotifierTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sends_release_alert_email_with_safe_context(): void
    {
        Mail::fake();

        DB::table('settings')->updateOrInsert(
            ['group' => 'general', 'name' => 'support_email'],
            [
                'payload' => json_encode('ops@vitobusinessos.test'),
                'locked' => false,
                'updated_at' => now(),
            ]
        );

        Cache::forget('settings.general');
        BaseSettings::clearRuntimeCache();

        $tenant = Tenant::factory()->create(['slug' => 'beta-shop']);

        app(ReleaseAlertNotifier::class)->notify(
            'new_checkout',
            new RuntimeException('Stripe session failed'),
            $tenant,
            request(),
            ['api_secret' => 'should-not-leak']
        );

        Mail::assertSent(ReleaseAlertMail::class, function (ReleaseAlertMail $mail) use ($tenant): bool {
            return $mail->hasTo('ops@vitobusinessos.test')
                && $mail->context['feature_key'] === 'new_checkout'
                && $mail->context['tenant_slug'] === $tenant->slug
                && $mail->context['extra']['api_secret'] === '[redacted]';
        });
    }
}
