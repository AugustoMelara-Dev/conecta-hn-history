<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Automation;

use App\Application\Automation\GraphBuilder;
use App\Application\Automation\TemplateCatalog;
use App\Application\Automation\WorkflowDefinitionService;
use App\Domain\Automation\Enums\TriggerType;
use App\Domain\Automation\ValueObjects\AutomationGraph;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * AutomationBuilder - Drag-and-drop workflow builder (Livewire + Alpine).
 *
 * El JSON del graph se almacena en graph_json; la UI real requiere
 * componente Alpine.js externo para drag-and-drop visual.
 */
class AutomationBuilder extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $title = 'Automation Builder';

    protected static string|\UnitEnum|null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Builder';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.app.pages.automation-builder';

    public ?array $data = [];

    public ?int $templateId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('1. Selecciona un template (opcional)')->components([
                    Select::make('template_slug')
                        ->label('Template')
                        ->options(fn () => collect((new TemplateCatalog)->all())->pluck('name', 'slug')->toArray())
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            if ($state) {
                                $template = (new TemplateCatalog)->findBySlug($state);
                                if ($template) {
                                    $this->data['name'] = $template['name'];
                                    $this->data['description'] = $template['description'];
                                    $this->data['graph_json'] = json_encode($template['graph'], JSON_PRETTY_PRINT);
                                }
                            }
                        }),
                ]),
                Section::make('2. Workflow')->components([
                    TextInput::make('workflow_key')->label('Clave')->required()->maxLength(100),
                    TextInput::make('name')->label('Nombre')->required()->maxLength(200),
                    Textarea::make('description')->label('Descripción')->rows(2)->maxLength(500),
                    Select::make('trigger_type')
                        ->label('Trigger')
                        ->options(TriggerType::options())
                        ->required()
                        ->native(false),
                ]),
                Section::make('3. Graph (JSON DAG)')->description('Edita los nodos y edges del workflow.')
                    ->components([
                        Textarea::make('graph_json')
                            ->label('Graph JSON')
                            ->rows(20)
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('{"nodes": [{"id": "t1", "type": "trigger", ...}], "edges": [{"from": "t1", "to": "a1"}]}'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('validate')
                ->label('Validar graph')
                ->color('info')
                ->action(function () {
                    $graphJson = $this->data['graph_json'] ?? '{}';
                    try {
                        $graph = AutomationGraph::fromJson($graphJson);
                        $builder = new GraphBuilder;
                        $result = $builder->validate($graph->nodes, $graph->edges);
                        if (empty($result)) {
                            Notification::make()->title('Graph válido')->success()->send();
                        } else {
                            Notification::make()->title('Errores: '.implode(', ', $result))->danger()->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()->title('JSON inválido: '.$e->getMessage())->danger()->send();
                    }
                }),
            Action::make('save')
                ->label('Guardar workflow')
                ->color('success')
                ->action(function () {
                    $tenantId = Filament::getTenant()?->getKey();
                    if (! $tenantId) {
                        Notification::make()->title('No tenant context')->danger()->send();

                        return;
                    }
                    $actorId = auth()->id();
                    if (! is_string($actorId)) {
                        abort(403);
                    }
                    $graph = AutomationGraph::fromJson((string) $this->data['graph_json']);
                    $errors = (new GraphBuilder)->validate($graph->nodes, $graph->edges);
                    if ($errors !== []) {
                        Notification::make()->title('El workflow no es válido')->body(implode("\n", $errors))->danger()->send();

                        return;
                    }
                    app(WorkflowDefinitionService::class)->createDraft(
                        (string) $tenantId,
                        Str::slug((string) $this->data['workflow_key']),
                        [
                            'name' => (string) $this->data['name'],
                            'description' => $this->data['description'] ?? null,
                            'trigger' => (string) $this->data['trigger_type'],
                            'graph' => $graph->toArray(),
                            'steps' => collect($graph->actionNodes())->map(static fn (array $node): array => [
                                'id' => (string) $node['id'],
                                'type' => 'tool',
                                'action' => (string) ($node['action_type'] ?? ''),
                                'provider' => 'dovra',
                                'tool' => (string) ($node['action_type'] ?? ''),
                                'tool_version' => 'v1',
                                'payload' => (array) ($node['config'] ?? []),
                            ])->all(),
                        ],
                        $actorId,
                    );
                    Notification::make()->title('Borrador canónico guardado')->success()->send();
                    $this->form->fill();
                }),
        ];
    }
}
