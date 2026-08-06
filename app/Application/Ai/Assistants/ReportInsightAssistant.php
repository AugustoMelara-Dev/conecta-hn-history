<?php\n\ndeclare(strict_types=1);\n\nnamespace App.Application.Ai.Assistants;\n\nclass ReportInsightAssistant extends BaseAssistant
{
    public function key(): string { return 'report_insight'; }
    public function name(): string { return 'Report Insights'; }
    public function description(): string { return 'Genera insights a partir de un reporte.'; }
    public function estimateCost(): float { return 0.05; }
    public function category(): string { return 'Analytics'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['report_key'])) {
            return AssistantResult::failure('Missing report_key');
        }
        return $this->mockOutput($input, [
            'insights' => [
                'Tendencia al alza en los últimos 3 meses',
                'Categoría X con mejor performance',
                'Oportunidad de optimización en canal Y',
            ],
            'recommendations' => ['Invertir más en canal Z', 'Revisar pricing de categoría X'],
        ]);
    }
}\n

