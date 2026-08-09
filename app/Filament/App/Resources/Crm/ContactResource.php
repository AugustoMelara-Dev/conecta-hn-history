<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Application\Crm\BulkOperationsService;
use App\Application\Crm\ContactEnrichmentService;
use App\Domain\Crm\Enums\LifecycleStage;
use App\Filament\App\Resources\Crm\ContactResource\Pages;
use App\Filament\App\Resources\Crm\ContactResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\App\Resources\Crm\ContactResource\RelationManagers\DealsRelationManager;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Contactos';

    protected static ?string $modelLabel = 'contacto';

    protected static ?string $pluralModelLabel = 'Contactos';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información personal')
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextInput::make('first_name')
                                    ->label('Nombre')
                                    ->maxLength(100),

                                TextInput::make('last_name')
                                    ->label('Apellido')
                                    ->maxLength(100),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(50),

                                TextInput::make('job_title')
                                    ->label('Cargo')
                                    ->maxLength(100),

                                Select::make('source')
                                    ->label('Fuente')
                                    ->options([
                                        'web' => 'Web',
                                        'referral' => 'Referido',
                                        'social' => 'Redes sociales',
                                        'email' => 'Email marketing',
                                        'paid' => 'Publicidad pagada',
                                        'cold' => 'Contacto frío',
                                    ])
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Clasificación')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('lifecycle_stage')
                                    ->label('Etapa del ciclo')
                                    ->options(LifecycleStage::options())
                                    ->default(LifecycleStage::Lead->value)
                                    ->required(),

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

                        DateTimePicker::make('last_contact_at')
                            ->label('Último contacto')
                            ->timezone('America/Tegucigalpa')
                            ->displayFormat('d/m/Y H:i'),
                    ]),

                Section::make('Notas y campos personalizados')
                    ->components([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Nombre')
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—'),

                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('lifecycle_stage')
                    ->label('Etapa')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LifecycleStage::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => LifecycleStage::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('owner.name')
                    ->label('Responsable')
                    ->placeholder('Sin asignar')
                    ->toggleable(),

                TextColumn::make('deals_count')
                    ->label('Deals')
                    ->counts('deals')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('activities_count')
                    ->label('Actividades')
                    ->counts('activities')
                    ->badge()
                    ->color('info'),

                TextColumn::make('last_contact_at')
                    ->label('Último contacto')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('lifecycle_stage')
                    ->label('Etapa')
                    ->options(LifecycleStage::options()),

                SelectFilter::make('owner_id')
                    ->label('Responsable')
                    ->relationship('owner', 'name'),

                TernaryFilter::make('has_deals')
                    ->label('Con deals')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('deals'),
                        false: fn (Builder $q) => $q->whereDoesntHave('deals'),
                    ),

                TernaryFilter::make('has_email')
                    ->label('Con email')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('email'),
                        false: fn (Builder $q) => $q->whereNull('email'),
                    ),
            ])
            ->actions([
                EditAction::make(),
                Action::make('enrich')
                    ->label('Enriquecer')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function (Contact $record) {
                        app(ContactEnrichmentService::class)
                            ->applyToContact($record);
                        Notification::make()
                            ->title('Contacto enriquecido')
                            ->success()
                            ->send();
                    }),
                Action::make('view_deals')
                    ->label('Ver deals')
                    ->icon('heroicon-o-currency-dollar')
                    ->url(fn (Contact $record) => DealResource::getUrl('index', ['tableFilters[contact_id][value]' => $record->id])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_assign_owner')
                        ->label('Asignar responsable')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('owner_id')
                                ->label('Responsable')
                                ->relationship('owner', 'name')
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $tenantId = Filament::getTenant()->getKey();
                            app(BulkOperationsService::class)->bulkAssignOwner(
                                $records->pluck('id')->toArray(),
                                $data['owner_id'],
                                $tenantId,
                            );
                            Notification::make()->title('Contactos reasignados')->success()->send();
                        }),
                    BulkAction::make('bulk_update_lifecycle')
                        ->label('Cambiar etapa')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('lifecycle_stage')
                                ->label('Nueva etapa')
                                ->options(LifecycleStage::options())
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $tenantId = Filament::getTenant()->getKey();
                            app(BulkOperationsService::class)->bulkUpdateLifecycleStage(
                                $records->pluck('id')->toArray(),
                                $data['lifecycle_stage'],
                                $tenantId,
                            );
                            Notification::make()->title('Etapas actualizadas')->success()->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tienes contactos aún')
            ->emptyStateDescription('Crea tu primer contacto para empezar tu pipeline CRM.')
            ->emptyStateIcon('heroicon-o-user-plus')
            ->defaultSort('created_at', 'desc');
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
            DealsRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
