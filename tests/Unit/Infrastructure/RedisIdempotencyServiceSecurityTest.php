<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Application\Tenant\Results\RegisterTenantResult;
use App\Infrastructure\Common\Idempotency\RedisIdempotencyService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class RedisIdempotencyServiceSecurityTest extends TestCase
{
    public function test_disallowed_cached_object_cannot_run_wakeup_gadget(): void
    {
        CachedWakeupProbe::$executed = false;
        $store = Cache::store('array');
        $store->put('idempotency:malicious', serialize([
            'status' => 'COMPLETED',
            'data' => new CachedWakeupProbe,
        ]), 60);

        $result = (new RedisIdempotencyService($store))->get('malicious');

        self::assertFalse(CachedWakeupProbe::$executed);
        self::assertNotInstanceOf(CachedWakeupProbe::class, $result);
    }

    public function test_explicitly_allowed_result_contract_can_be_replayed(): void
    {
        $service = new RedisIdempotencyService(Cache::store('array'));
        $expected = new RegisterTenantResult(
            tenantId: 'tenant-1',
            domain: 'negocio',
            ownerEmail: 'owner@example.test',
            setupUrl: 'https://conecta.test/app/negocio',
        );

        $service->store('allowed-result', $expected);

        self::assertEquals($expected, $service->get('allowed-result'));
        self::assertSame('COMPLETED', $service->getState('allowed-result'));
    }
}

final class CachedWakeupProbe
{
    public static bool $executed = false;

    public function __wakeup(): void
    {
        self::$executed = true;
    }
}
