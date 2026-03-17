<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        app()->instance(
            \App\Application\Auth\Validators\DeleteUserCommandValidator::class,
            new \App\Application\Auth\Validators\DeleteUserCommandValidator(
                new class implements \App\Application\Auth\Handlers\DeleteUserHandlerInterface
                {
                    public function handle(\App\Application\Auth\Commands\DeleteUserCommand $command): \App\Application\Auth\Results\DeleteUserResult
                    {
                        return new \App\Application\Auth\Results\DeleteUserResult('ok', $command->userId);
                    }
                },
                app(\App\Application\Shared\Contracts\ValidatorFactoryInterface::class)
            )
        );
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenantContext = app(\App\Domain\Shared\Services\TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setTenant((string) $tenant->id);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ]);

        $response
            ->assertSessionHasNoErrors();

        $response->assertRedirect('/profile');

        $this->assertTrue(Hash::check('New-Password-123', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        app()->instance(
            \App\Application\Auth\Validators\DeleteUserCommandValidator::class,
            new \App\Application\Auth\Validators\DeleteUserCommandValidator(
                new class implements \App\Application\Auth\Handlers\DeleteUserHandlerInterface
                {
                    public function handle(\App\Application\Auth\Commands\DeleteUserCommand $command): \App\Application\Auth\Results\DeleteUserResult
                    {
                        return new \App\Application\Auth\Results\DeleteUserResult('ok', $command->userId);
                    }
                },
                app(\App\Application\Shared\Contracts\ValidatorFactoryInterface::class)
            )
        );
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenantContext = app(\App\Domain\Shared\Services\TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setTenant((string) $tenant->id);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}
