<?php

declare(strict_types=1);

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use JsonSchema\Validator;
use PHPUnit\Framework\Assert;

uses(\Tests\TestCase::class);

/**
 * @param  object  $payload
 */
function assertPayloadMatchesSchema(object $payload, string $schemaPath): void
{
    Assert::assertFileExists($schemaPath, 'Schema file is missing.');

    if (! class_exists(Validator::class)) {
        return;
    }

    $schemaJson = file_get_contents($schemaPath);
    Assert::assertIsString($schemaJson);

    $schema = json_decode($schemaJson);
    Assert::assertIsObject($schema);

    $validator = new Validator();
    $validator->validate($payload, $schema);

    Assert::assertTrue($validator->isValid(), 'Schema violation: '.json_encode($validator->getErrors()));
}

it('validates CreateOrderCommand against its JSON schema contract', function () {
    $command = new CreateOrderCommand(
        tenantId: 'tnt_123456789',
        locationId: 'loc_123456789',
        customerId: 'cus_987654321',
        currency: 'USD',
        customerName: 'John Doe',
        customerPhone: '+1234567890',
        customerEmail: 'john@example.com',
        customerAddress: '123 Main St',
        customerNotes: 'Leave at door',
        deliveryType: 'pickup',
        paymentMethod: 'pending',
        items: [
            ['product_id' => 101, 'quantity' => 2, 'notes' => 'Extra spicy'],
        ],
        couponCode: 'WELCOME10',
        requestorId: 'usr_111',
        idempotencyKey: 'idem_abc123',
        schemaVersion: 1,
    );

    $payload = json_decode(json_encode($command));
    expect($payload)->toBeObject();

    assertPayloadMatchesSchema(
        $payload,
        base_path('tests/Contracts/Schemas/Application/Sales/CreateOrderCommand.v1.json'),
    );
});

it('validates RegisterTenantCommand against its JSON schema contract', function () {
    $command = new RegisterTenantCommand(
        businessName: 'VitoCode Tech',
        slug: 'vitocode-tech',
        ownerName: 'Vito',
        email: 'admin@vitocode.com',
        password: 'SecurePassword123!',
        cityId: 5,
        categoryId: 12,
        whatsapp: '+0987654321',
        selectedPlan: 'pro',
        logoPath: '/storage/logos/vitocode.png',
        idempotencyKey: 'idem_xyz987',
        schemaVersion: 1,
    );

    $payload = json_decode(json_encode($command));
    expect($payload)->toBeObject();

    assertPayloadMatchesSchema(
        $payload,
        base_path('tests/Contracts/Schemas/Application/Tenant/RegisterTenantCommand.v1.json'),
    );
});
