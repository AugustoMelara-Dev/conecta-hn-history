<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Auth;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationToLoginFlowTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_completes_full_registration_and_allows_login(): void
    {
        $email = 'architect@titanium.app';
        $plainPassword = 'SecretTitanium123!';
        $name = 'System Architect';
        $tenantName = 'Titanium Architecture';
        $tenantDomain = 'titanium-arch';

        $command = new RegisterUserCommand(
            name: $name,
            email: $email,
            password: $plainPassword,
            tenantName: $tenantName,
            slug: $tenantDomain,
            source: 'automated_test'
        );

        /** @var RegisterUserHandlerInterface $handler */
        $handler = $this->app->make(RegisterUserHandlerInterface::class);

        /** @var RegisterUserResult $result */
        $result = $handler->handle($command);

        $this->assertSame($email, $result->email);

        $this->assertDatabaseHas('tenants', [
            'id' => $result->tenantId,
            'slug' => $tenantDomain,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $result->id,
            'email' => $email,
            'tenant_id' => $result->tenantId,
        ]);

        $loginSuccess = Auth::attempt([
            'email' => $email,
            'password' => $plainPassword,
        ]);

        $this->assertTrue($loginSuccess, 'Auth::attempt failed. Password hash projection mismatch or user not found.');

        $authenticatedUser = Auth::user();

        $this->assertNotNull($authenticatedUser, 'Auth::user() returned null after successful attempt.');
        $this->assertEquals($result->id, $authenticatedUser->getAuthIdentifier(), 'Authenticated User ID mismatch.');
        $this->assertEquals(
            $result->tenantId,
            $authenticatedUser->tenant_id,
            'Authenticated User is not linked to the correct Tenant.'
        );
    }

    #[Test]
    public function it_complies_with_the_v1_json_schema_contract(): void
    {
        $command = new RegisterUserCommand(
            name: 'Schema Compliant User',
            email: 'schema@titanium.app',
            password: 'StrongPassword123!',
            source: 'user_registration',
            tenantId: null
        );

        $serialized = json_decode(json_encode($command) ?: 'null');

        $this->assertIsObject($serialized);
        $this->assertSame('Schema Compliant User', $serialized->name);
        $this->assertNull($serialized->tenantId);

        if (! class_exists(Validator::class)) {
            $this->assertFileExists(base_path('tests/Schemas/Commands/RegisterUserCommand.v1.json'));

            return;
        }

        $schemaPath = base_path('tests/Schemas/Commands/RegisterUserCommand.v1.json');
        $this->assertFileExists($schemaPath);

        $schemaJson = file_get_contents($schemaPath);
        $this->assertIsString($schemaJson);

        $schemaObject = json_decode($schemaJson);
        $this->assertIsObject($schemaObject);

        $validator = new Validator;
        $validator->validate($serialized, $schemaObject);

        $this->assertTrue($validator->isValid(), 'RegisterUserCommand JSON schema validation failed.');
    }
}
