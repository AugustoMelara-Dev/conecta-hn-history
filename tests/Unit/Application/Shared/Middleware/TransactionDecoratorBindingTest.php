<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('confirms the command bus execution is wrapped by the transaction decorator', function () {
    $pipeline = app(\App\Application\Auth\Handlers\VerifyOtpHandlerInterface::class);
    $stack = [$pipeline];
    $visited = [];
    $containsTransactionDecorator = false;

    while ($stack !== []) {
        /** @var object $current */
        $current = array_pop($stack);

        $objectId = spl_object_id($current);

        if (isset($visited[$objectId])) {
            continue;
        }

        $visited[$objectId] = true;

        if ($current instanceof \App\Application\Shared\Common\Decorators\TransactionDecorator) {
            $containsTransactionDecorator = true;
            break;
        }

        $reflection = new ReflectionObject($current);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            if (! $property->isInitialized($current)) {
                continue;
            }

            $value = $property->getValue($current);

            if (is_object($value)) {
                $stack[] = $value;
            }
        }
    }

    expect($containsTransactionDecorator)->toBeTrue();
});
