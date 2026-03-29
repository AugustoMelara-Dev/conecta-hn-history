<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileTimelineStateReader
{
    public function __construct(
        private readonly CustomerProfileTimelineSectionReader $timelineReader,
    ) {}

    /**
     * @return array{empty:string,items:list<array{type:string,title:string,copy:string,channel:?string,when:string,color:string}>}
     */
    public function read(?CustomerProfile $profile): array
    {
        if (! $profile instanceof CustomerProfile) {
            return [
                'empty' => 'Todavia no hay timeline disponible.',
                'items' => [],
            ];
        }

        $items = $this->timelineReader->items($profile);

        return [
            'empty' => $items === [] ? 'Aun no hay eventos suficientes para armar la historia operativa de este cliente.' : '',
            'items' => $items,
        ];
    }
}
