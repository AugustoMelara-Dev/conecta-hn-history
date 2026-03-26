<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Marketing\Jobs\DispatchCampaignSegment;
use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\TenantGovernance\Services\TenantCampaignDispatchPlanner;
use App\Domain\TenantGovernance\Services\TenantCampaignWorkspaceReader;
use App\Models\Campaign;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationLabel(): string
    {
        return 'Campanas';
    }

    public static function getModelLabel(): string
    {
        return 'Campana';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Campanas';
    }

    public static function getTitleCaseModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing PRO';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = self::getTenantRecord();

        return $tenant instanceof Tenant && (bool) $tenant->is_pro;
    }

    public static function canAccess(): bool
    {
        $tenant = self::getTenantRecord();

        return $tenant instanceof Tenant && (bool) $tenant->is_pro;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Detalles de la campana')
                    ->description('Disena el mensaje y define si sale por email o por WhatsApp.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Canal')
                                    ->options([
                                        'email' => 'Email',
                                        'whatsapp' => 'WhatsApp',
                                    ])
                                    ->required()
                                    ->default('email')
                                    ->native(false),
                                Forms\Components\Select::make('audience_type')
                                    ->label('Audiencia base')
                                    ->options(fn (): array => collect(AudienceType::cases())
                                        ->mapWithKeys(fn (AudienceType $type) => [$type->value => $type->label()])
                                        ->all())
                                    ->required()
                                    ->default(AudienceType::CRM_CUSTOMERS->value)
                                    ->native(false),
                            ]),
                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto o nombre interno')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ejemplo: Reactiva clientes VIP de marzo'),
                        Forms\Components\Textarea::make('content')
                            ->label('Mensaje')
                            ->required()
                            ->rows(8)
                            ->placeholder('Escribe el mensaje principal o el contexto comercial de esta campana.')
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('whatsapp_template_name')
                                    ->label('Template de WhatsApp')
                                    ->placeholder('promo_reactivation_es')
                                    ->visible(fn ($get): bool => $get('type') === 'whatsapp'),
                                Forms\Components\TextInput::make('whatsapp_template_language')
                                    ->label('Idioma template')
                                    ->default('es_HN')
                                    ->visible(fn ($get): bool => $get('type') === 'whatsapp'),
                            ]),
                    ]),
                Section::make('Segmentacion CRM')
                    ->description('Filtra la audiencia por segmento, fidelidad, reward, sede y owner de cartera.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('customer_segment')
                                    ->label('Segmento')
                                    ->options([
                                        'vip' => 'VIP',
                                        'repeat' => 'Recurrente',
                                        'reactivation' => 'Reactivacion',
                                        'booked_service' => 'Con citas',
                                        'active' => 'Activo',
                                        'prospect' => 'Prospecto',
                                        'new' => 'Nuevo',
                                    ])
                                    ->placeholder('Toda la base elegible')
                                    ->native(false),
                                Forms\Components\Select::make('loyalty_tier')
                                    ->label('Tier de fidelidad')
                                    ->options([
                                        'vip' => 'VIP',
                                        'gold' => 'Gold',
                                        'silver' => 'Silver',
                                        'base' => 'Base',
                                    ])
                                    ->placeholder('Cualquier tier')
                                    ->native(false),
                                Forms\Components\Select::make('loyalty_reward_id')
                                    ->label('Reward destacado')
                                    ->options(fn (): array => static::rewardOptions())
                                    ->searchable()
                                    ->placeholder('Sin reward especial')
                                    ->helperText('Si eliges uno, la campana puede empujar un canje o una meta clara de puntos.')
                                    ->native(false),
                                Forms\Components\Toggle::make('only_redeemable_rewards')
                                    ->label('Solo clientes listos para canjear')
                                    ->helperText('Cuando hay reward destacado, usa el costo real del reward para filtrar.'),
                                Forms\Components\Select::make('location_id')
                                    ->label('Sede')
                                    ->options(fn (): array => static::locationOptions())
                                    ->searchable()
                                    ->placeholder('Todas las sedes')
                                    ->native(false),
                                Forms\Components\Select::make('owner_user_id')
                                    ->label('Responsable')
                                    ->options(fn (): array => static::teamOptions())
                                    ->searchable()
                                    ->placeholder('Toda la cartera')
                                    ->native(false),
                            ]),
                        Forms\Components\Toggle::make('requires_marketing_opt_in')
                            ->label('Exigir opt-in marketing')
                            ->default(true),
                    ]),
                Section::make('Cadencia comercial')
                    ->description('Programa el envio y, si quieres, dejalo corriendo con una cadencia real.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('Primer envio')
                                    ->seconds(false)
                                    ->helperText('Si queda vacio, la campana se guarda en borrador hasta lanzarla manualmente.'),
                                Forms\Components\Toggle::make('is_recurring')
                                    ->label('Cadencia recurrente')
                                    ->inline(false),
                                Forms\Components\Select::make('cadence_interval_days')
                                    ->label('Repeticion')
                                    ->options([
                                        1 => 'Diaria',
                                        3 => 'Cada 3 dias',
                                        7 => 'Semanal',
                                        14 => 'Quincenal',
                                        30 => 'Mensual',
                                    ])
                                    ->placeholder('Sin repeticion')
                                    ->required(fn ($get): bool => (bool) $get('is_recurring'))
                                    ->visible(fn ($get): bool => (bool) $get('is_recurring'))
                                    ->native(false),
                            ]),
                    ]),
                Section::make('Playbooks sugeridos por tu vertical')
                    ->description('Usa estas cadencias como punto de partida para campañas de recompra, reactivacion o upsell.')
                    ->schema([
                        Forms\Components\Placeholder::make('cadence_playbooks_hint')
                            ->hiddenLabel()
                            ->content(fn () => static::cadencePlaybooksHint()),
                    ]),
                Section::make('Despacho')
                    ->schema([
                        Forms\Components\Placeholder::make('recipients_count')
                            ->label('Destinatarios')
                            ->content(fn (?Campaign $record) => $record ? $record->recipients_count.' contactos' : 'Se calculara al enviar'),
                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Ultimo envio')
                            ->content(fn (?Campaign $record) => $record && $record->sent_at ? $record->sent_at->format('d/m/Y H:i') : 'Aun no enviada'),
                        Forms\Components\Placeholder::make('next_run_at')
                            ->label('Proxima corrida')
                            ->content(fn (?Campaign $record) => $record && $record->next_run_at ? $record->next_run_at->format('d/m/Y H:i') : 'Sin agenda activa'),
                    ])
                    ->columns(3)
                    ->hidden(fn (string $operation) => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Campana')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Campaign $record): string => $record->audience_summary),
                Tables\Columns\TextColumn::make('type')
                    ->label('Canal')
                    ->badge()
                    ->state(fn (Campaign $record): string => $record->type_label)
                    ->color(fn (Campaign $record): string => $record->type === 'whatsapp' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('cadence_label')
                    ->label('Cadencia')
                    ->badge()
                    ->color(fn (Campaign $record): string => $record->usesCadence() ? 'warning' : ($record->scheduled_at ? 'info' : 'gray')),
                Tables\Columns\TextColumn::make('loyaltyReward.name')
                    ->label('Reward')
                    ->default('Sin reward')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recipients_count')
                    ->label('Destinatarios')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sending' => 'info',
                        'queued' => 'warning',
                        'scheduled' => 'warning',
                        'failed' => 'danger',
                        'sent' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Ultimo envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Proxima corrida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'scheduled' => 'Programada',
                        'queued' => 'En cola',
                        'sending' => 'Enviando',
                        'sent' => 'Enviada',
                        'failed' => 'Fallida',
                    ]),
                Tables\Filters\Filter::make('recurring')
                    ->label('Con cadencia')
                    ->query(fn ($query) => $query->where('is_recurring', true)),
                Tables\Filters\Filter::make('reward')
                    ->label('Con reward')
                    ->query(fn ($query) => $query->whereNotNull('loyalty_reward_id')),
            ])
            ->actions([
                Action::make('send')
                    ->label(fn (Campaign $record): string => $record->status === 'scheduled' ? 'Lanzar ahora' : 'Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Despachar campana')
                    ->modalDescription(fn (Campaign $record) => $record->usesCadence()
                        ? 'Se lanzara ahora y, como tiene cadencia activa, seguira corriendo con la repeticion configurada.'
                        : 'Se enviara esta campana a la audiencia filtrada. ¿Continuamos?')
                    ->visible(fn (Campaign $record) => $record->status !== 'sending')
                    ->action(function (Campaign $record) {
                        $tenant = self::getTenantRecord();
                        if (! $tenant instanceof Tenant) {
                            return;
                        }

                        $dispatch = app(TenantCampaignDispatchPlanner::class)->planDispatch($tenant, $record);
                        $audienceCount = $dispatch['audience_count'];

                        if ($audienceCount === 0) {
                            Notification::make()
                                ->title('Audiencia vacia')
                                ->warning()
                                ->body('No hay contactos elegibles con estos filtros y opt-in.')
                                ->send();

                            return;
                        }

                        app(TenantCampaignDispatchPlanner::class)->queueCampaign($record, $dispatch['next_run_at']);

                        DispatchCampaignSegment::dispatch($record->id, (string) $tenant->getKey());

                        Notification::make()
                            ->title($dispatch['uses_cadence'] ? 'Cadencia activada' : 'Campana en cola')
                            ->success()
                            ->body($dispatch['uses_cadence']
                                ? "La campana se lanzo ahora y seguira viva para {$audienceCount} destinatarios elegibles."
                                : "Se procesara el envio a {$audienceCount} destinatarios en breve.")
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Marketing PRO',
                'heading' => 'Todavia no has creado campanas',
                'description' => 'Crea campanas por segmento, tier, reward, sede y owner usando la base real de tu CRM.',
                'buttonLabel' => 'Crear campana',
                'buttonUrl' => static::getUrl('create'),
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\App\Resources\CampaignResource\Pages\ListCampaigns::route('/'),
            'create' => \App\Filament\App\Resources\CampaignResource\Pages\CreateCampaign::route('/create'),
            'edit' => \App\Filament\App\Resources\CampaignResource\Pages\EditCampaign::route('/{record}/edit'),
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @return array<string, string>
     */
    private static function teamOptions(): array
    {
        $tenant = self::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return self::workspaceReader()->teamOptions($tenant);
    }

    /**
     * @return array<string, string>
     */
    private static function locationOptions(): array
    {
        $tenant = self::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return self::workspaceReader()->locationOptions($tenant);
    }

    /**
     * @return array<string, string>
     */
    private static function rewardOptions(): array
    {
        $tenant = self::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return self::workspaceReader()->rewardOptions($tenant);
    }

    private static function cadencePlaybooksHint(): HtmlString|string
    {
        $tenant = self::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return 'Selecciona un tenant valido para cargar recomendaciones de cadence.';
        }

        return self::workspaceReader()->cadencePlaybooksHint($tenant);
    }

    private static function workspaceReader(): TenantCampaignWorkspaceReader
    {
        return app(TenantCampaignWorkspaceReader::class);
    }
}
