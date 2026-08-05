<?php

declare(strict_types=1);

it('keeps order aggregate writes owned by the canonical writer', function (): void {
    $root = dirname(__DIR__, 2);
    $legacyRepository = $root.'/app/Infrastructure/Repositories/Sales/EloquentOrderRepository.php';
    $tenantRepository = file_get_contents(
        $root.'/app/Infrastructure/Tenant/EloquentOrderRepository.php',
    );
    $stateService = file_get_contents($root.'/app/Services/OrderStateService.php');

    expect(file_exists($legacyRepository))->toBeFalse()
        ->and($tenantRepository)->not->toContain('updateStatus(')
        ->and($stateService)
        ->toContain('CanonicalOrderWriter')
        ->not->toContain('$order->update(')
        ->not->toContain('OrderEvent::record');
});

it('has no order state service callers outside its compatibility boundary', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $violations = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        if (str_ends_with($path, '/Services/OrderStateService.php')) {
            continue;
        }
        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'use App\\Services\\OrderStateService')
            || str_contains($contents, 'app(OrderStateService::class)')) {
            $violations[] = $path;
        }
    }

    expect($violations)->toBe([]);
});

it('blocks direct order and order item persistence outside documented owners', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $violations = [];
    $allowed = [
        str_replace('\\', '/', $root.'/Application/Orders/CanonicalOrderWriter.php'),
        str_replace('\\', '/', $root.'/Application/Payments/Actions/RefundOrderPaymentAction.php'),
        str_replace('\\', '/', $root.'/Services/WebhookService.php'),
    ];
    $patterns = [
        '/\bnew\s+Order\b/',
        '/\bOrder::(?:create|updateOrCreate|firstOrCreate|upsert|insert|update|delete)\s*\(/',
        '/->items\(\)->(?:create|delete|save|update)\s*\(/',
        '/->orders\(\)->(?:create|delete|save|update)\s*\(/',
        '/\$order->(?:save|update|delete)\s*\(/',
        '/\$order->forceFill\([\s\S]{0,500}?->save\s*\(/',
        '/DB::table\([\'"](?:orders|order_items)[\'"]\)[\s\S]{0,300}?->(?:insert|insertGetId|update|delete|upsert)\s*\(/',
    ];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        if (in_array($path, $allowed, true)) {
            continue;
        }
        $contents = '';
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $contents .= is_array($token) ? $token[1] : $token;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = $path;
                break;
            }
        }
    }

    expect(array_values(array_unique($violations)))->toBe([]);
});
