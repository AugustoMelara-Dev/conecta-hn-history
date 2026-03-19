<?php

declare(strict_types=1);

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Payments\Commands\HandlePaymentWebhookCommand;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Tenant\Results\UpdateScheduleResult;

it('CreateOrderCommand PII properties have SensitiveParameter attribute', function () {
    $reflection = new ReflectionClass(CreateOrderCommand::class);
    $params = $reflection->getConstructor()->getParameters();
    $paramsByName = array_combine(
        array_map(fn ($p) => $p->getName(), $params),
        $params
    );
    foreach (['customerPhone', 'customerEmail', 'customerAddress'] as $propName) {
        $param = $paramsByName[$propName];
        $attributes = array_map(fn ($a) => $a->getName(), $param->getAttributes());
        expect($attributes)->toContain('SensitiveParameter');
    }
});

it('VerifyOtpCommand PII properties have SensitiveParameter attribute', function () {
    $reflection = new ReflectionClass(VerifyOtpCommand::class);
    $params = $reflection->getConstructor()->getParameters();
    $paramsByName = array_combine(
        array_map(fn ($p) => $p->getName(), $params),
        $params
    );
    foreach (['identifier', 'code', 'ipAddress'] as $propName) {
        $param = $paramsByName[$propName];
        $attributes = array_map(fn ($a) => $a->getName(), $param->getAttributes());
        expect($attributes)->toContain('SensitiveParameter');
    }
});

it('HandlePaymentWebhookCommand tenantId is string type', function () {
    $reflection = new ReflectionClass(HandlePaymentWebhookCommand::class);
    $property = $reflection->getProperty('tenantId');
    $type = $property->getType();
    expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
});

it('UpdateScheduleResult tenantId is string type', function () {
    $reflection = new ReflectionClass(UpdateScheduleResult::class);
    $property = $reflection->getProperty('tenantId');
    $type = $property->getType();
    expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
});

it('UpdateScheduleResult fromArray accepts string tenantId', function () {
    $reflection = new ReflectionClass(UpdateScheduleResult::class);
    $method = $reflection->getMethod('fromArray');
    $params = $method->getParameters();
    $tenantParam = collect($params)->firstWhere(fn ($p) => $p->getName() === 'tenantId');
    $type = $tenantParam?->getType();
    expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
});
