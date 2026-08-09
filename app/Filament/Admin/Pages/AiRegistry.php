<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Application\Ai\AssistantExecutor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\LaravelSettings\Facades\Settings;

/**
 * AiRegistry - Manage providers, models, costs.
 */
class AiRegistry extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI Registry';

    protected static ?string $title = 'AI Registry';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.admin.pages.ai-registry';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configurar AI')->components([
                Select::make('provider')->label('Provider')->options(['openai' => 'OpenAI', 'anthropic' => 'Anthropic'])->default('openai'),
                TextInput::make('model')->label('Modelo')->default('gpt-4o-mini'),
                TextInput::make('api_key_ref')->label('API key reference'),
                TextInput::make('input_cost_per_million')->label('Input cost ($/M tokens)')->numeric()->default(0.15),
                TextInput::make('output_cost_per_million')->label('Output cost ($/M tokens)')->numeric()->default(0.60),
            ]),
        ])->statePath('data');
    }

    public function getAssistants(): array
    {
        try {
            $executor = app(AssistantExecutor::class);
            $assistants = [];
            foreach ($executor->all() as $assistant) {
                $assistants[] = [
                    'key' => $assistant->key(),
                    'name' => $assistant->name(),
                    'category' => $assistant->category(),
                    'cost' => $assistant->estimateCost(),
                ];
            }

            return $assistants;
        } catch (\Throwable) {
            return [];
        }
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Settings::set('ai.provider', $state['provider']);
        Settings::set('ai.model', $state['model']);
        Notification::make()->title('AI Registry actualizado')->success()->send();
    }
}
