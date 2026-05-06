<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  TEST ISOLATION: Hermetic Environment Setup                              ║
     * ╠══════════════════════════════════════════════════════════════════════════╣
     * ║  This setUp() disables side-effects that conflict with RefreshDatabase: ║
     * ║    1. Spatie Activity Logger (via config override)                       ║
     * ║    2. All Model Observers (via withoutModelEvents in specific tests)     ║
     * ║  Tests requiring observers should explicitly re-enable them.             ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Vite manifest during tests
        $this->withoutVite();

        // ═══════════════════════════════════════════════════════════════════════
        // KILL SWITCH: Disable Spatie Activity Logger (Defense in Depth)
        // ═══════════════════════════════════════════════════════════════════════
        config(['activitylog.enabled' => false]);
    }

    /**
     * Run callback without model events (for use in individual tests).
     *
     * Usage in tests:
     * $this->withoutModelEvents(function() {
     * Tenant::factory()->create();
     * });
     */
    protected function withoutModelEvents(callable $callback): mixed
    {
        return Model::withoutEvents($callback);
    }
}
