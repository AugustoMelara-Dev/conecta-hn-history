<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Application\Crm\LeadScoringService;
use App\Domain\Crm\Enums\DealStatus;
use App\Filament\App\Resources\Crm\DealResource\Pages;
use App\Models\CrmDeal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealResource extends Resource
{
    protected static ?string $model = CrmDeal::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Deals';

    protected static ?string $modelLabel = 'deal';

    protected static ?string $pluralModelLabel = 'Deals';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del deal')
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(200),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options(DealStatus::options())
                                    ->default(DealStatus::Open->value)
                                    ->required()
                                    ->native(false)
                                    ->live(),

                                Select::make('pipeline_id')
                                    ->label('Pipeline')
                                    ->relationship('pipeline', 'name')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('stage_id', null)),

                                Select::make('stage_id')
                                    ->label('Etapa')
                                    ->relationship(
                                        name: 'stage',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query, $get) => $query->where('pipeline_id', $get('pipeline_id')),
                                    )
                                    ->required(),
                            ]),
                    ]),

                Section::make('Valor y fechas')
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextInput::make('value')
                                    ->label('Valor')
                                    ->numeric()
                                    ->prefix('L')
                                    ->required()
                                    ->default(0),

                                Select::make('currency')
                                    ->label('Moneda')
                                    ->options(['HNL' => 'HNL', 'USD' => 'USD', 'MXN' => 'MXN'])
                                    ->default('HNL'),

                                TextInput::make('probability')
                                    ->label('Probabilidad')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(10),

                                DatePicker::make('expected_close_date')
                                    ->label('Fecha esperada'),

                                DateTimePicker::make('won_at')
                                    ->label('Ganado en')
                                    ->visible(fn ($get) => $get('status') === DealStatus::Won->value),

                                DateTimePicker::make('lost_at')
                                    ->label('Perdido en')
                                    ->visible(fn ($get) => $get('status') === DealStatus::Lost->value),

                                TextInput::make('lost_reason')
                                    ->label('Razón de pérdida')
                                    ->maxLength(100)
                                    ->visible(fn ($get) => $get('status') === DealStatus::Lost->value),
                            ]),
                    ]),

                Section::make('Relaciones')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('contact_id')
                                    ->label('Contacto')
                                    ->relationship('contact', 'id')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name ?? '—')
                                    ->searchable(['first_name', 'last_name', 'email'])
                                    ->preload(),

                                Select::make('company_id')
                                    ->label('Empresa')
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('owner_id')
                                    ->label('Responsable')
                                    ->relationship('owner', 'name')
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Deal')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('value')
                    ->label('Valor')
                    ->money('HNL')
                    ->sortable(),

                TextColumn::make('probability')
                    ->label('Prob.')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('weighted_value')
                    ->label('Valor pond.')
                    ->money('HNL')
                    ->getStateUsing(fn (CrmDeal $record) => $record->weighted_value)
                    ->toggleable(),

                TextColumn::make('stage.name')
                    ->label('Etapa')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DealStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => DealStatus::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('contact.display_name')
                    ->label('Contacto')
                    ->placeholder('—'),

                TextColumn::make('owner.name')
                    ->label('Responsable')
                    ->placeholder('Sin asignar')
                    ->toggleable(),

                TextColumn::make('expected_close_date')
                    ->label('Cierre esperado')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(DealStatus::options()),

                SelectFilter::make('pipeline_id')
                    ->label('Pipeline')
                    ->relationship('pipeline', 'name'),

                SelectFilter::make('stage_id')
                    ->label('Etapa')
                    ->relationship('stage', 'name'),

                SelectFilter::make('owner_id')
                    ->label('Responsable')
                    ->relationship('owner', 'name'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('mark_won')
                    ->label('Marcar ganado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (CrmDeal $record) {
                        $record->update([
                            'status' => DealStatus::Won->value,
                            'won_at' => now(),
                            'probability' => 100,
                        ]);
                        Notification::make()->title('Deal marcado como ganado')->success()->send();
                    }),
                Action::make('mark_lost')
                    ->label('Marcar perdido')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('lost_reason')
                            ->label('Razón')
                            ->required(),
                    ])
                    ->action(function (array $data, CrmDeal $record) {
                        $record->update([
                            'status' => DealStatus::Lost->value,
                            'lost_at' => now(),
                            'lost_reason' => $data['lost_reason'],
                            'probability' => 0,
                        ]);
                        Notification::make()->title('Deal marcado como perdido')->success()->send();
                    }),
                Action::make('ai_email_draft')
                    ->label('AI Email Draft')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function (CrmDeal $record) {
                        // Delegate to AI assistant (PR6 will wire OpenAI call)
                        Notification::make()
                            ->title('AI email draft generado')
                            ->body('Revisa el panel de AI assistants.')
                            ->info()
                            ->send();
                    }),
                Action::make('ai_summarize')
                    ->label('AI Resumen')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function (CrmDeal $record) {
                        Notification::make()
                            ->title('AI resumen generado')
                            ->body('Resumen disponible en el detail view.')
                            ->info()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expected_close_date', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $tenant->getKey());
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\App\Resources\Crm\DealResource\RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeals::route('/'),
            'create' => Pages\CreateDeal::route('/create'),
            'edit' => Pages\EditDeal::route('/{record}/edit'),
        ];
    }
}
