<?php

declare(strict_types=1);

it('InventoryManager implements InventoryManagerInterface', function (): void {
    expect(\App\Services\InventoryManager::class)
        ->toImplement(\App\Contracts\InventoryManagerInterface::class);
});
