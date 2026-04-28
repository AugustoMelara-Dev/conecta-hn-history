<?php

declare(strict_types=1);

use App\Contracts\Scope\TenantContextInterface;

test('deprecated order money and otp APIs are not used internally', function () {
    $violations = deprecated_code_matches([
        '/transitionTo\s*\(/',
        '/->amount\s*\(/',
        '/verifyCode\s*\(/',
    ]);

    expect($violations)->toBe([]);
});

test('tenant context scope consumers use tenant uuid contract', function () {
    $reflection = new ReflectionClass(TenantContextInterface::class);

    expect($reflection->hasMethod('getTenantUuid'))->toBeTrue()
        ->and($reflection->hasMethod('getTenantId'))->toBeFalse();
});

test('new generic exception catches must be added to the documented allowlist', function () {
    $allowed = [
        'app/Console/Commands/CheckSubscriptionExpiry.php',
        'app/Console/Commands/CleanOrphanedUploads.php',
        'app/Console/Commands/ExpireSubscriptions.php',
        'app/Console/Commands/MediaCleanupCommand.php',
        'app/Console/Commands/SubscriptionLifecycleCommand.php',
        'app/Console/Commands/SystemPruneCommand.php',
        'app/Filament/App/Pages/EditTenantProfile.php',
        'app/Filament/App/Pages/ManageMarketing.php',
        'app/Http/Controllers/Auth/SocialAuthController.php',
        'app/Http/Controllers/Public/ReviewController.php',
        'app/Http/Middleware/TrackTenantVisit.php',
        'app/Http/Middleware/TrackTenantView.php',
        'app/Http/Middleware/TrackVisits.php',
        'app/Infrastructure/Http/Controllers/MockApiController.php',
        'app/Infrastructure/Listeners/Booking/CheckWaitlistOnCancellation.php',
        'app/Infrastructure/Listeners/Sales/SendOrderConfirmationNotification.php',
        'app/Observers/CategoryObserver.php',
        'app/Observers/CityObserver.php',
        'app/Observers/CouponObserver.php',
        'app/Observers/MenuSectionObserver.php',
        'app/Observers/TenantObserver.php',
        'app/Services/QrCodeService.php',
        'app/Services/WebhookService.php',
    ];

    $violations = deprecated_code_matches(['/catch\s*\(\s*\\\\?Exception(?:\s+\$[A-Za-z_][A-Za-z0-9_]*)?\s*\)/']);
    $unexpected = collect($violations)
        ->reject(fn (string $violation): bool => in_array(str($violation)->before(':')->value(), $allowed, true))
        ->values()
        ->all();

    expect($unexpected)->toBe([]);
});

/**
 * @param  list<string>  $patterns
 * @return list<string>
 */
function deprecated_code_matches(array $patterns): array
{
    $basePath = realpath(__DIR__.'/../../app');
    expect($basePath)->not()->toBeFalse();

    $matches = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator((string) $basePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = str_replace('\\', '/', str_replace(dirname((string) $basePath).DIRECTORY_SEPARATOR, '', $path));
        $contents = (string) file_get_contents($path);

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $found, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($found[0] as $match) {
                $line = substr_count(substr($contents, 0, (int) $match[1]), PHP_EOL) + 1;
                $matches[] = "{$relativePath}:{$line}:{$match[0]}";
            }
        }
    }

    sort($matches);

    return $matches;
}
