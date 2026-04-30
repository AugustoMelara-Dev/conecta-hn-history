<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CustomerMessageTemplateResource\Pages;
use App\Models\CustomerMessageTemplate;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerMessageTemplateResource extends Resource
{
    protected static ?string $model = CustomerMessageTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function canViewAny(): bool
    {
        return static::getTenantRecord() instanceof Tenant;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return 'Plantillas CRM';
    }

    public static function getModelLabel(): string
    {
        return 'Plantilla CRM';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Plantillas CRM';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = CustomerMessageTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_active', true)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Contexto de la plantilla')
                    ->description('Define a quién aplica, por qué canal sale y si debe reemplazar una respuesta del sistema.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre interno')
                                    ->required()
                                    ->maxLength(160),
                                Forms\Components\TextInput::make('template_key')
                                    ->label('Clave')
                                    ->required()
                                    ->maxLength(120)
                                    ->helperText('Usa la misma clave de una plantilla del sistema si quieres overridearla.')
                                    ->placeholder('close_sale')
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? str($state)->trim()->replace(' ', '_')->replace('-', '_')->lower()->value()
                                        : null),
                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('channel')
                                    ->label('Canal')
                                    ->options([
                                        'email' => 'Email',
                                        'whatsapp' => 'WhatsApp',
                                    ])
                                    ->required()
                                    ->default('email')
                                    ->native(false),
                                Forms\Components\Select::make('customer_segment')
                                    ->label('Segmento del cliente')
                                    ->options(static::segmentOptions())
                                    ->placeholder('Todos los segmentos')
                                    ->native(false),
                                Forms\Components\Select::make('business_segment_key')
                                    ->label('Rubro del negocio')
                                    ->options(static::businessSegmentOptions())
                                    ->searchable()
                                    ->placeholder('Cualquier rubro')
                                    ->native(false),
                                Forms\Components\TextInput::make('automation_key')
                                    ->label('Automation key')
                                    ->required()
                                    ->maxLength(120)
                                    ->default('manual_follow_up'),
                                Forms\Components\TextInput::make('priority')
                                    ->label('Prioridad del override')
                                    ->numeric()
                                    ->default(100)
                                    ->helperText('Menor numero gana cuando hay varias plantillas elegibles.'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activa')
                                    ->default(true),
                                Forms\Components\Toggle::make('requires_marketing_opt_in')
                                    ->label('Exigir opt-in de marketing')
                                    ->default(false),
                            ]),
                    ]),
                Section::make('Mensaje y CTA')
                    ->description('Este contenido es el que vera el cliente si la plantilla se usa desde la bandeja o un journey.')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->visible(fn ($get): bool => $get('channel') === 'email')
                            ->required(fn ($get): bool => $get('channel') === 'email')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('body')
                            ->label('Mensaje')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('cta_label')
                            ->label('CTA visible')
                            ->maxLength(120),
                    ]),
                Section::make('Proveedor y control')
                    ->description('Configura proveedor de plantilla, variables y límites básicos de uso.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('template_name')
                                    ->label('Template del proveedor')
                                    ->required(fn ($get): bool => $get('channel') === 'whatsapp')
                                    ->visible(fn ($get): bool => $get('channel') === 'whatsapp')
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('template_language')
                                    ->label('Idioma template')
                                    ->default('es_HN')
                                    ->visible(fn ($get): bool => $get('channel') === 'whatsapp')
                                    ->maxLength(16),
                                TagsInput::make('template_variables')
                                    ->label('Variables del template')
                                    ->placeholder('customer_name')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('metadata.daily_limit')
                                    ->label('Limite diario')
                                    ->numeric()
                                    ->default(1),
                                Forms\Components\TextInput::make('metadata.retry_limit')
                                    ->label('Retries maximos')
                                    ->numeric()
                                    ->default(1),
                                Forms\Components\TextInput::make('metadata.retry_backoff_minutes')
                                    ->label('Backoff en minutos')
                                    ->numeric()
                                    ->default(30),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->latest('priority')->latest('updated_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Plantilla')
                    ->searchable(['name', 'template_key', 'description'])
                    ->weight('bold')
                    ->description(fn (CustomerMessageTemplate $record): string => static::performanceSummary($record)),
                Tables\Columns\TextColumn::make('template_key')
                    ->label('Clave')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('channel_label')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (CustomerMessageTemplate $record): string => $record->channel === 'whatsapp' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('segment_label')
                    ->label('Segmento')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('business_segment_label')
                    ->label('Rubro')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                    ]),
                Tables\Filters\SelectFilter::make('customer_segment')
                    ->label('Segmento')
                    ->options(static::segmentOptions()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerMessageTemplates::route('/'),
            'create' => Pages\CreateCustomerMessageTemplate::route('/create'),
            'edit' => Pages\EditCustomerMessageTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function segmentOptions(): array
    {
        return [
            'vip' => 'VIP',
            'repeat' => 'Recurrente',
            'reactivation' => 'Reactivación',
            'booked_service' => 'Con citas',
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'new' => 'Nuevo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function businessSegmentOptions(): array
    {
        return app(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class)
            ->rubroLabels();
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected static function performanceSummary(CustomerMessageTemplate $record): string
    {
        $query = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $record->tenant_id)
            ->where('template_key', (string) $record->template_key)
            ->where('created_at', '>=', now()->subDays(30));

        $sent = (clone $query)->where('status', CustomerTouchpoint::STATUS_SENT)->count();
        $replied = (clone $query)->where('delivery_status', CustomerTouchpoint::DELIVERY_REPLIED)->count();

        return "{$sent} envíos / {$replied} respuestas en 30 días";
    }
}
