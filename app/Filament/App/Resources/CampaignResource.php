<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Marketing\CampaignDispatchService;
use App\Filament\App\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campañas';

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'Campañas';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información de la campaña')->components([
                TextInput::make('title')->label('Nombre interno')->required()->maxLength(255),
                Select::make('type')
                    ->label('Canal')
                    ->required()
                    ->options(['email' => 'Email'])
                    ->default('email')
                    ->helperText('WhatsApp, SMS y push se habilitarán cuando exista un proveedor configurado.'),
            ])->columns(2),

            Section::make('Contenido')->components([
                TextInput::make('subject')->label('Asunto del correo')->required()->maxLength(255),
                RichEditor::make('content')->label('Contenido')->required()->columnSpanFull(),
            ]),

            Section::make('Programación')->components([
                Grid::make(2)->components([
                    DateTimePicker::make('scheduled_at')
                        ->label('Programada para')
                        ->timezone('America/Tegucigalpa')
                        ->displayFormat('d/m/Y H:i'),
                    TextInput::make('recipients_count')
                        ->label('Destinatarios capturados')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('type')->label('Canal')->badge()->color('info'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'info',
                        'dispatching' => 'warning',
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_at')->label('Programada')->dateTime('d/m/Y H:i')->timezone('America/Tegucigalpa')->sortable(),
                TextColumn::make('recipients_count')->label('Audiencia')->numeric()->sortable(),
                TextColumn::make('delivered_count')->label('Entregados')->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    'draft' => 'Borrador',
                    'scheduled' => 'Programada',
                    'dispatching' => 'Enviando',
                    'completed' => 'Completada',
                    'completed_with_errors' => 'Con incidencias',
                    'failed' => 'Fallida',
                ]),
            ])
            ->actions([
                Action::make('schedule')
                    ->label('Programar')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn (Campaign $record): bool => in_array($record->status, ['draft', 'paused'], true) && $record->scheduled_at !== null)
                    ->action(fn (Campaign $record) => app(CampaignDispatchService::class)->schedule($record, $record->scheduled_at)),
                Action::make('send_now')
                    ->label('Enviar ahora')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record): bool => in_array($record->status, ['draft', 'paused', 'scheduled'], true))
                    ->action(fn (Campaign $record) => app(CampaignDispatchService::class)->schedule($record, now())),
                Action::make('pause')
                    ->label('Pausar')
                    ->icon('heroicon-o-pause')
                    ->visible(fn (Campaign $record): bool => $record->status === 'scheduled')
                    ->action(fn (Campaign $record) => app(CampaignDispatchService::class)->pause($record)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No tienes campañas creadas')
            ->emptyStateDescription('Crea una campaña, define el contenido y programa el envío.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateActions([CreateAction::make()->label('Crear primera campaña')->icon('heroicon-o-plus')])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        return $tenant ? $query->where('tenant_id', $tenant->getKey()) : $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
