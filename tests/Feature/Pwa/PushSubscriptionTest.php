<?php

declare(strict_types=1);

use App\Models\User;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * PWA Infrastructure Tests
 *
 * Verifies that Web Push notification code infrastructure is properly configured.
 */
describe('PWA Infrastructure', function () {
    it('User model uses HasPushSubscriptions trait', function () {
        $traits = class_uses_recursive(User::class);

        expect($traits)->toContain(HasPushSubscriptions::class);
    });

    it('User has updatePushSubscription method', function () {
        expect(method_exists(User::class, 'updatePushSubscription'))->toBeTrue();
    });

    it('User has pushSubscriptions relationship', function () {
        expect(method_exists(User::class, 'pushSubscriptions'))->toBeTrue();
    });

    it('VitePWA is configured in vite.config.js', function () {
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        expect($viteConfig)
            ->toContain('VitePWA')
            ->toContain('manifest')
            ->toContain('workbox');
    });

    it('PWA meta tags exist in app.blade.php', function () {
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        expect($layout)
            ->toContain('theme-color')
            ->toContain('manifest');
    });
});
