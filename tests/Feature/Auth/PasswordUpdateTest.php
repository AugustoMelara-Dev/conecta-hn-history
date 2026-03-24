<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_password_can_be_updated(): void
    {
        $token = 'password-update-token';
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
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                '_token' => $token,
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
        $token = 'password-update-invalid-token';
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
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                '_token' => $token,
                'current_password' => 'wrong-password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}
