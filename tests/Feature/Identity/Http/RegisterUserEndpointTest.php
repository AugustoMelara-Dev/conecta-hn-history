<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterUserEndpointTest extends TestCase
{
    // USO DE MIGRACIONES REALES PARA EVITAR DEADLOCKS TRANSACCIONALES
    // Esto destruye y recrea las tablas por cada test, eliminando conflictos de bloqueo.
    use RefreshDatabase;

    private string $endpoint = '/api/v1/register';

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_a_user_successfully_and_persists_data()
    {
        // 1. Verbose Exception Rule (Happy Path)
        $this->withoutExceptionHandling();

        // 2. Preparation (Happy Path Payload)
        $payload = [
            'name' => 'John Titanium',
            'email' => 'john.architect@titanium.app',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'tenant_name' => 'Titanium Systems',
            'tenant_domain' => 'titanium-sys',
        ];

        // 3. Execution
        $response = $this->postJson($this->endpoint, $payload);

        // 4. Verification (Strict HTTP Semantics)
        $response->assertCreated();

        // Integrity Structural Check
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'email',
                    'tenant_id',
                    'requires_verification',
                ],
            ],
        ]);

        // Physical Persistence Audit (Real Disk Write verification)
        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => $payload['tenant_domain'],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_input_formats()
    {
        // Critical: Exception Handling MUST be active (Implicit) here.

        // 1. Preparation (Dirty Payload)
        $payload = [
            'name' => 'John',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'tenant_name' => 'Titanium Inc',
            'tenant_domain' => 'invalid domain space',
        ];

        // 2. Execution
        $response = $this->postJson($this->endpoint, $payload);

        // 3. Verification (Input Defense)
        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
            'password',
            'tenant_domain',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_duplicate_registration_proving_sql_projection()
    {
        // Fase 1: Siembra (Verbose Mode for Debugging)
        // Desactivamos excepciones para asegurar que el primer usuario entre limpiamente.
        $this->withoutExceptionHandling();

        $payload = [
            'name' => 'Alfa User',
            'email' => 'alfa@test.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'tenant_name' => 'Alfa Corp',
            'tenant_domain' => 'alfa-corp',
        ];

        // Ensure first request is successful
        $this->postJson($this->endpoint, $payload)->assertCreated();

        // Fase 2: Ataque (Hybrid Toggle - Reactivate Exceptions for 422)
        // NECESARIO: Reactivar manejo de errores. Ahora esperamos que el sistema
        // lance una excepción por duplicado y Laravel la convierta en 422.
        $this->withExceptionHandling();

        $response = $this->postJson($this->endpoint, $payload);

        // Fase 3: Veredicto (Projection Proof)
        $response->assertUnprocessable();

        // Validación de Fuente: Confirma que SQL informó al Validador sobre el conflicto.
        $response->assertJsonValidationErrors([
            'email',
            'tenant_domain',
        ]);
    }
}
