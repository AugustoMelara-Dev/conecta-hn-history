<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;

class ReportInsightAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'report_insight';
    }

    public function name(): string
    {
        return 'Report Insights';
    }

    public function description(): string
    {
        return 'Genera insights a partir de un reporte.';
    }

    public function estimateCost(): float
    {
        return 0.05;
    }

    public function category(): string
    {
        return 'Analytics';
    }

    public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult
    {
        if (! isset($input['report_key'])) {
            return AssistantResult::failure('Missing report_key');
        }

        return $this->executeCanonical($input, $context);
    }
}
