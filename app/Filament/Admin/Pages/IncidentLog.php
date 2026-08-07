<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * IncidentLog - Manual incident log.
 */
class IncidentLog extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string | \UnitEnum | null $navigationGroup = 'Auditoría';
    protected static ?string $navigationLabel = 'Incidentes';
    protected static ?string $title = 'Incident Log';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.admin.pages.incident-log';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nuevo incidente')->components([
                TextInput::make('title')->label('Título')->required(),
                Select::make('severity')->label('Severidad')->options(['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'])->required(),
                Textarea::make('description')->label('Descripción')->rows(4)->required(),
            ]),
        ])->statePath('data');
    }

    public function logIncident(): void
    {
        $state = $this->form->getState();
        DB::table('activity_log')->insert([
            'log_name' => 'incident',
            'description' => $state['title'],
            'event' => $state['severity'],
            'properties' => json_encode(['description' => $state['description']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Notification::make()->title('Incidente registrado')->success()->send();
        $this->form->fill();
    }

    public function getRecentIncidents(): array
    {
        return DB::table('activity_log')
            ->where('log_name', 'incident')
            ->orderBy('created_at', 'desc')->limit(20)->get()->toArray();
    }
}
