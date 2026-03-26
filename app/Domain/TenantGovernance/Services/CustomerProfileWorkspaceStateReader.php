<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceStateReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceReader $workspaceReader,
    ) {}

    /**
     * @return array{empty:string,cards:list<array{label:string,value:string,copy:string}>}
     */
    public function read(?CustomerProfile $profile): array
    {
        if (! $profile instanceof CustomerProfile) {
            return [
                'empty' => 'Abre una ficha real para ver el workspace consolidado del cliente.',
                'cards' => [],
            ];
        }

        return [
            'empty' => '',
            'cards' => $this->workspaceReader->workspaceCards($profile),
        ];
    }
}
