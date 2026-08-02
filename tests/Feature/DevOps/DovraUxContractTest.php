<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DovraUxContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    public function test_program_ten_fixture_keeps_brand_truth_and_non_honduras_market_selection_visible(): void
    {
        $fixture = $this->fixture();

        self::assertSame('Dovra AI', $fixture['brand']['name']);
        self::assertSame('Dovra', $fixture['brand']['shortName']);

        self::assertSame(['products', 'services', 'hybrid'], array_column($fixture['scenarios'], 'key'));
        self::assertNotEmpty(array_filter($fixture['scenarios'], static function (array $scenario): bool {
            return array_reduce($scenario['marketOptions'], static function (bool $carry, array $market): bool {
                return $carry || ($market['isHonduras'] === false);
            }, false);
        }));

        foreach ($fixture['scenarios'] as $scenario) {
            self::assertNotEmpty($scenario['buyer']['reversibleCopy']);
            self::assertNotEmpty($scenario['buyer']['liveMessage']);
            self::assertNotEmpty($scenario['onboarding']['authoritativeNote']);
        }
    }

    public function test_client_acknowledgement_alone_does_not_mark_authoritative_onboarding_complete(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'description' => null,
            'whatsapp_number' => null,
            'is_active' => false,
        ]);

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $wizard = $service->firstRunWizardFor($tenant, $owner);
        $firstStepKey = (string) ($wizard['currentStepKey'] ?? '');

        self::assertNotSame('', $firstStepKey);
        self::assertFalse((bool) $wizard['completed']);

        $advanced = $service->completeFirstRunStep($tenant->fresh(), $owner, $firstStepKey);

        self::assertFalse((bool) $advanced['completed']);
        self::assertSame($firstStepKey, (string) $advanced['currentStepKey']);
        self::assertNotContains($firstStepKey, $advanced['completedSteps']);
        self::assertSame(0, count($advanced['completedSteps']));
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(resource_path('stories/dovra-program-10.fixtures.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function setTenantContext(Tenant $tenant): void
    {
        if (method_exists($this, 'actingAsTenant')) {
            $this->actingAsTenant((string) $tenant->id);

            return;
        }

        if (class_exists(\App\Domain\Shared\Services\TenantContext::class)) {
            $context = app(\App\Domain\Shared\Services\TenantContext::class);

            if (method_exists($context, 'reset')) {
                $context->reset();
            }

            if (method_exists($context, 'setAsSystemRoot')) {
                $context->setAsSystemRoot();
            }
        }
    }
}
