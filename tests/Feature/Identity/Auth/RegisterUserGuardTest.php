<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Auth;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Domain\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegisterUserGuardTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_domain_exception_when_tenant_id_is_null_on_staff_registration_path()
    {
        // ARRANGE: Command intended for STAFF role but missing tenantId
        $command = new RegisterUserCommand(
            name: 'Lost Staff',
            email: 'lost@staff.com',
            password: 'StrongPassword123!',
            tenantId: null, // MISSING
            role: UserRole::STAFF->value, // Explicitly requesting STAFF
            source: 'test_guard'
        );

        // ASSERT: Expect DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tenantId is required for tenant-bound user registration.');

        // ACT
        $handler = $this->app->make(RegisterUserHandlerInterface::class);
        $handler->handle($command);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_succeeds_when_tenant_id_is_null_on_seller_registration_path()
    {
        // ARRANGE: Command intended for SELLER/OWNER role, missing tenantId (valid for new tenant creation)
        $command = new RegisterUserCommand(
            name: 'New Seller',
            email: 'seller@new.com',
            password: 'StrongPassword123!',
            tenantId: null, // Valid here
            role: \App\Domain\Identity\Enums\UserRole::TENANT_OWNER->value, // Fix Enum usage
            source: 'test_creation'
        );

        // ACT
        $handler = $this->app->make(RegisterUserHandlerInterface::class);
        $result = $handler->handle($command);

        // ASSERT
        $this->assertDatabaseHas('users', ['email' => 'seller@new.com']);
        $this->assertDatabaseHas('tenants', ['id' => $result->tenantId]);
    }
}
