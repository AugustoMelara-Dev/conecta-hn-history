<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Auth;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RegistrationToLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_completes_full_registration_cycle_and_allows_login()
    {
        // 0. STERILE ENVIRONMENT CONFIGURATION
        // Ensure we are starting fresh (handled by RefreshDatabase)

        // Raw Data Definitions
        $email = 'architect@titanium.app';
        $plainPassword = 'SecretTitanium123!';
        $name = 'System Architect';
        $tenantName = 'Titanium Architecture';
        $tenantDomain = 'titanium-arch'; // slug

        // FASE 1: EJECUCIÓN DEL COMANDO (El Disparo)

        // 1.1 Instantiate Command
        $command = new RegisterUserCommand(
            name: $name,
            email: $email,
            password: $plainPassword,
            tenantName: $tenantName,
            slug: $tenantDomain,
            source: 'automated_test'
        );

        // 1.2 Resolve Handler (No Mocking allowed - Metal Real)
        /** @var RegisterUserHandlerInterface $handler */
        $handler = $this->app->make(RegisterUserHandlerInterface::class);

        // 1.3 Execute Handle
        /** @var RegisterUserResult $result */
        $result = $handler->handle($command);

        // 1.4 Intermediate Validation
        $this->assertNotNull($result);
        $this->assertIsString($result->id);
        $this->assertIsString($result->tenantId);
        $this->assertEquals($email, $result->email);

        // FASE 2: AUDITORÍA DE PROYECCIÓN (La Evidencia Física)

        // 2.1 Verify Tenant Projection
        $this->assertDatabaseHas('tenants', [
            'id' => $result->tenantId,
            'slug' => $tenantDomain, // DB column is 'slug', not 'domain'
        ]);

        // 2.2 Verify User Projection (excluding password hash)
        $this->assertDatabaseHas('users', [
            'id' => $result->id,
            'email' => $email,
            'tenant_id' => $result->tenantId,
        ]);

        // FASE 3: PRUEBA DE FUEGO (El Login)

        // 3.1 Attempt Login with Plain Credentials
        // This validates that the hasher used in domain matches the driver used by Auth facade
        $loginSuccess = Auth::attempt([
            'email' => $email,
            'password' => $plainPassword,
        ]);

        // 3.2 Assertion of Access
        $this->assertTrue($loginSuccess, 'Auth::attempt failed. Password hash projection mismatch or user not found.');

        // 3.3 Verification of Session & Identity
        $authenticatedUser = Auth::user();

        $this->assertNotNull($authenticatedUser, 'Auth::user() returned null after successful attempt.');
        $this->assertEquals($result->id, $authenticatedUser->getAuthIdentifier(), 'Authenticated User ID mismatch.');

        // 3.4 Verification of Multi-Tenant Relation
        // Check if the Eloquent model has tenant_id attribute accessible
        $this->assertEquals(
            $result->tenantId,
            $authenticatedUser->tenant_id,
            'Authenticated User is not linked to the correct Tenant.'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_complies_with_the_v1_json_schema_contract()
    {
        $command = new RegisterUserCommand(
            name: 'Schema Compliant User',
            email: 'schema@titanium.app',
            password: 'StrongPassword123!',
            source: 'user_registration',
            tenantId: null // Explicitly present as null
        );

        $serialized = json_decode(json_encode($command));

        expect($serialized)->toBeObject();
        expect($serialized->name)->toBe('Schema Compliant User');
        expect($serialized->tenantId)->toBeNull();
        expect($serialized)->toMatchJsonSchema('tests/Schemas/Commands/RegisterUserCommand.v1.json');
    }
}
