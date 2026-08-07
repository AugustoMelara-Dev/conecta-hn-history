<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class DashboardNarrativeAssistant extends BaseAssistant
{
    public function key(): string { return 'dashboard_narrative'; }
    public function name(): string { return 'Dashboard Narrative'; }
    public function description(): string { return 'Genera narrativa a partir de widgets del dashboard.'; }
    public function estimateCost(): float { return 0.02; }
    public function category(): string { return 'Analytics'; }

    public function execute(array $input): AssistantResult
    {
        return $this->mockOutput($input, [
            'narrative' => "Tus ventas crecieron 12% este mes, con un ticket promedio de L 250. Los productos top son...",
            'highlights' => ['Crecimiento sostenido', 'Ticket promedio estable'],
            'concerns' => ['Bajo inventario en categor�a X'],
        ]);
    }
}
