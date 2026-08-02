<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DovraOnboardingJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    #[DataProvider('journeyModes')]
    public function test_product_service_and_hybrid_journeys_recompute_authoritative_progress(
        string $businessType,
        string $commercialMode,
        array $setup,
        string $expectedBusinessLabelFragment,
    ): void {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER->value,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create(array_merge([
            'business_type' => $businessType,
            'commercial_mode' => $commercialMode,
            'description' => 'Negocio de prueba con autoridad real.',
            'whatsapp_number' => '+50499990000',
            'is_active' => true,
        ], $setup['tenant'] ?? []));

        if ($setup['product'] ?? false) {
            Product::factory()->forTenant($tenant)->create([
                'is_visible' => true,
            ]);
        }

        if ($setup['service'] ?? false) {
            Service::factory()->create([
                'tenant_id' => $tenant->getKey(),
                'is_active' => true,
            ]);
        }

        if ($setup['schedule'] ?? false) {
            $tenant->forceFill([
                'simple_schedule' => 'Lunes a viernes 08:00 - 17:00',
            ])->save();
        }

        $this->setTenantContext($tenant);
        $service = app(TenantOnboardingProgressService::class);
        $snapshot = $service->persist($tenant->fresh(), $owner);

        self::assertSame(UserRole::TENANT_OWNER->value, $snapshot['role']);
        self::assertGreaterThan(0, (int) $snapshot['progress']);
        self::assertNotEmpty($snapshot['milestones']);
        self::assertNotSame('#', (string) $snapshot['nextAction']['href']);
        self::assertStringContainsString($expectedBusinessLabelFragment, (string) $snapshot['businessLabel']);

        $wizard = $service->firstRunWizardFor($tenant->fresh(), $owner);
        self::assertNotEmpty($wizard['steps']);

        $completedStepsBeforeAck = array_values(array_filter(
            array_map(
                static fn (array $step): ?string => ($step['completed'] ?? false) ? (string) $step['key'] : null,
                (array) $wizard['steps'],
            ),
            static fn (?string $step): bool => $step !== null,
        ));

        $acknowledged = $service->completeFirstRunStep($tenant->fresh(), $owner, (string) $wizard['currentStepKey']);
        self::assertSame((string) $wizard['currentStepKey'], (string) $acknowledged['currentStepKey']);
        self::assertSame($completedStepsBeforeAck, array_values(array_filter(
            $acknowledged['completedSteps'],
            static fn (mixed $step): bool => is_string($step),
        )));
    }

    public static function journeyModes(): iterable
    {
        yield 'products' => [
            'retail',
            'products',
            ['product' => true, 'schedule' => true],
            'Productos',
        ];

        yield 'services' => [
            'service',
            'appointments',
            ['service' => true, 'schedule' => true],
            'Servicios',
        ];

        yield 'hybrid' => [
            'service',
            'hybrid',
            ['product' => true, 'service' => true, 'schedule' => true],
            'mixta',
        ];
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
