<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Handlers\RegisterTenantHandler;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Models\Category;
use App\Models\City;
use App\Models\Payment;
use App\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Throwable;

final class ProRegistrationDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        config()->set('subscriptions.plans.pro.price', 63900);
        $this->seedGeneralSettings();
        $this->app->bind(OutboxRepositoryInterface::class, fn () => new class implements OutboxRepositoryInterface
        {
            public function append(object $event): void
            {
                // Diagnostic boundary: no external outbox delivery.
            }

            public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): Collection
            {
                return collect();
            }
        });
    }

    public function test_pro_handler_reaches_payment_repository_with_a_closed_fake(): void
    {
        $payments = new ClosedPaymentRepository;
        $this->app->instance(PaymentRepositoryInterface::class, $payments);
        $handler = $this->app->make(RegisterTenantHandler::class);

        $result = $handler->handle($this->proCommand());

        self::assertTrue($payments->createCalled);
        self::assertSame(63900, $payments->createdPayment?->amount->amount);
        self::assertSame('diagnostic-pro', $result->domain);
    }

    public function test_real_payment_repository_accepts_the_registration_fixture(): void
    {
        $tenant = Tenant::factory()->create();
        $failure = new ClosedFailureCapture;
        $payment = null;

        try {
            $payment = $this->app->make(EloquentPaymentRepository::class)
                ->create($this->paymentFixture((string) $tenant->id));
        } catch (Throwable $exception) {
            $failure->record('payment_repository', $exception);
        }

        self::assertNull($failure->exceptionClass, $failure->diagnostic());
        self::assertInstanceOf(PaymentEntity::class, $payment);
        self::assertTrue(Str::isUuid($payment->id));
        self::assertSame(63900, $payment->amount->amount);

        $tenantContext = app(TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setTenant((string) $tenant->id);

        try {
            $canonicalActivity = Activity::query()
                ->where('subject_type', 'payment')
                ->where('subject_id', $payment->id)
                ->firstOrFail();
            self::assertSame($payment->id, (string) $canonicalActivity->subject?->getKey());

            $legacyActivityId = DB::table('activity_log')->insertGetId([
                'log_name' => 'pagos',
                'description' => 'legacy payment diagnostic',
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'properties' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $legacyActivity = Activity::query()->findOrFail($legacyActivityId);
            self::assertSame($payment->id, (string) $legacyActivity->subject?->getKey());
            self::assertSame(
                Payment::class,
                DB::table('activity_log')->where('id', $legacyActivityId)->value('subject_type'),
            );
        } finally {
            $tenantContext->reset();
        }
    }

    public function test_real_http_pro_registration_crosses_payment_stage_without_a_closed_failure(): void
    {
        $failure = new ClosedFailureCapture;
        $innerBus = $this->app->make(CommandBusInterface::class);
        $this->app->instance(CommandBusInterface::class, new ClosedDiagnosticCommandBus($innerBus, $failure));
        [$city, $category] = $this->referenceData();

        $response = $this
            ->withSession(['_token' => 'test-token'])
            ->post(route('tenant.register.store'), [
                '_token' => 'test-token',
                'business_name' => 'Diagnostic Pro',
                'slug' => 'diagnostic-pro-http',
                'name' => 'Diagnostic Owner',
                'email' => 'diagnostic.pro.http@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'city_id' => $city->id,
                'category_id' => $category->id,
                'commercial_mode' => 'products',
                'whatsapp' => '+504 9999-0000',
                'selected_plan' => 'pro',
                'terms_accepted' => true,
                'privacy_accepted' => true,
                'terms_version' => '2026-05-10',
                'privacy_version' => '2026-05-10',
            ]);

        self::assertNull($failure->exceptionClass, $failure->diagnostic());
        $response->assertRedirect('/app/diagnostic-pro-http');
        $tenant = Tenant::query()->where('slug', 'diagnostic-pro-http')->firstOrFail();
        $payment = Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        self::assertFalse((bool) $tenant->is_pro);
        self::assertSame('semilla', $tenant->plan_type?->value ?? $tenant->plan_type);
        self::assertSame('pending', $payment->status);
        self::assertSame('639.00', $payment->amount);
    }

    private function proCommand(): RegisterTenantCommand
    {
        return new RegisterTenantCommand(
            businessName: 'Diagnostic Pro',
            slug: 'diagnostic-pro',
            ownerName: 'Diagnostic Owner',
            email: 'diagnostic.pro.handler@example.com',
            password: 'Password123!',
            cityId: null,
            categoryId: null,
            whatsapp: null,
            selectedPlan: 'pro',
            businessType: 'retail',
            commercialMode: 'products',
        );
    }

    /** @return array<string, mixed> */
    private function paymentFixture(string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'amount' => 639.0,
            'bank' => 'pending_activation',
            'status' => 'pending',
            'proof_path' => 'pending',
            'reference_number' => 'DIAGNOSTIC-PRO',
        ];
    }

    /** @return array{0: City, 1: Category} */
    private function referenceData(): array
    {
        $suffix = (string) str()->uuid();
        $cityId = DB::table('cities')->insertGetId([
            'name' => 'San Pedro Sula',
            'slug' => 'diagnostic-city-'.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Servicios',
            'slug' => 'diagnostic-category-'.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [City::query()->findOrFail($cityId), Category::query()->findOrFail($categoryId)];
    }

    private function seedGeneralSettings(): void
    {
        foreach ([
            'pro_plan_price' => 500.0,
            'grace_period_days' => 7,
            'free_plan_product_limit' => 10,
            'pro_plan_product_limit' => 100,
            'bank_transfer_instructions' => 'Transferencia bancaria',
            'support_whatsapp_number' => '+50499990000',
            'support_email' => 'soporte@example.com',
            'maintenance_mode' => false,
            'allow_registrations' => true,
        ] as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'general', 'name' => $name],
                [
                    'locked' => false,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}

final class ClosedFailureCapture
{
    public ?string $stage = null;

    public ?string $exceptionClass = null;

    public function record(string $stage, Throwable $exception): void
    {
        $this->stage = $stage;
        $this->exceptionClass = $exception::class;
    }

    public function diagnostic(): string
    {
        return json_encode([
            'stage' => $this->stage,
            'exception_class' => $this->exceptionClass,
        ], JSON_THROW_ON_ERROR);
    }
}

final readonly class ClosedDiagnosticCommandBus implements CommandBusInterface
{
    public function __construct(
        private CommandBusInterface $inner,
        private ClosedFailureCapture $failure,
    ) {}

    public function dispatch(object $command): mixed
    {
        try {
            return $this->inner->dispatch($command);
        } catch (Throwable $exception) {
            $this->failure->record('command_dispatch', $exception);

            throw $exception;
        }
    }
}

final class ClosedPaymentRepository implements PaymentRepositoryInterface
{
    public bool $createCalled = false;

    public ?PaymentEntity $createdPayment = null;

    public function create(array $attributes): PaymentEntity
    {
        $this->createCalled = true;

        return $this->createdPayment = new PaymentEntity(
            id: 'diagnostic-payment',
            tenantId: (string) $attributes['tenant_id'],
            amount: new Money((int) round(((float) $attributes['amount']) * 100), 'HNL'),
            bank: (string) $attributes['bank'],
            referenceNumber: (string) $attributes['reference_number'],
            status: PaymentStatus::PENDING,
            proofUrl: (string) $attributes['proof_path'],
            createdAt: new DateTimeImmutable,
            idempotencyKey: 'diagnostic-payment',
        );
    }

    public function save(PaymentEntity $payment): void {}

    public function updateOptimistically(PaymentEntity $payment): void {}

    public function findById(string $id): ?PaymentEntity
    {
        return null;
    }

    public function findByIdWithTenant(string $id): ?array
    {
        return null;
    }

    public function findByIdOnWriteConnection(string $id): ?PaymentEntity
    {
        return null;
    }

    public function findByIdempotencyKey(string $tenantId, string $idempotencyKey): ?PaymentEntity
    {
        return null;
    }

    public function findPaymentsWithTemporaryProofs(int $limit = 50): Collection
    {
        return collect();
    }
}
