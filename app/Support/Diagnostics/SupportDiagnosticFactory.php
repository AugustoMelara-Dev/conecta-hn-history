<?php

declare(strict_types=1);

namespace App\Support\Diagnostics;

use App\Application\Shared\Contracts\TraceContextInterface;

final class SupportDiagnosticFactory
{
    public function __construct(
        private readonly TraceContextInterface $traceContext,
    ) {}

    /**
     * @return array{surface:string,status:string,probable_cause:string,recommended_action:string,trace_id:string}
     */
    public function make(
        string $surface,
        string $status,
        string $probableCause,
        string $recommendedAction
    ): array {
        return [
            'surface' => $surface,
            'status' => $status,
            'probable_cause' => $probableCause,
            'recommended_action' => $recommendedAction,
            'trace_id' => $this->traceContext->getTraceId(),
        ];
    }
}
