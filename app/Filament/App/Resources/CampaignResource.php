<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Marketing\Enums\AudienceType;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationLabel(): string
    {
        return 'Campañas';
    }

    public static function getModelLabel(): string
    {
        return 'Campaña';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Campañas';
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
                Section::make('Detalles de la campaña')
                    ->schema([
                        Forms\Components\Select::make('audience_type')
                            ->label('Destinatarios (Audiencia)')
                            ->options(fn (): array => collect(AudienceType::cases())
                                ->mapWithKeys(fn (AudienceType $type) => [$type->value => $type->label()])
                                ->all())
                            ->required()
                            ->default(AudienceType::FOLLOWERS->value)
                            ->helperText('¿A qué segmento deseas enviar esta campaña?'),

                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Black Friday! 50% de descuento'),

                        Forms\Components\RichEditor::make('content')
                            ->label('Contenido')
                            ->required()
                            ->placeholder('Escribe tu mensaje aquí...')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),
                    ]),

                Section::make('Estadisticas')
                    ->schema([
                        Forms\Components\Placeholder::make('recipients_count')
                            ->label('Destinatarios')
                            ->content(fn (?Campaign $record) => $record ? $record->recipients_count.' personas' : 'Se calculará al enviar'),

                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Fecha de envío')
                            ->content(fn (?Campaign $record) => $record && $record->sent_at ? $record->sent_at->format('d/m/Y H:i') : 'Aún no enviada'),
                    ])
                    ->columns(2)
                    ->hidden(fn (string $operation) => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('audience_type')
                    ->label('Audiencia')
                    ->formatStateUsing(fn (?AudienceType $state) => $state?->label() ?? 'Seguidores')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipients_count')
                    ->label('Destinatarios')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Envío')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ? $state->format('d/m/Y H:i') : 'Pendiente'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('send')
                    ->label('Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar campaña')
                    ->modalDescription(fn (Campaign $record) => 'Se enviará esta campaña al segmento seleccionado. ¿Estás seguro?')
                    ->visible(fn (Campaign $record) => ! $record->sent_at)
                    ->action(function (Campaign $record) {
                        $tenant = self::getTenantRecord();
                        if (! $tenant instanceof Tenant) {
                            return;
                        }

                        $audienceCount = self::calculateAudienceCount($tenant, $record->audience_type);

                        if ($audienceCount === 0) {
                            Notification::make()
                                ->title('Audiencia vacía')
                                ->warning()
                                ->body('No tienes contactos en este segmento para enviar la campaña.')
                                ->send();

                            return;
                        }

                        \App\Application\Marketing\Jobs\DispatchCampaignSegment::dispatch($record->id, (string) $tenant->getKey());

                        $record->update([
                            'status' => 'queued',
                        ]);

                        Notification::make()
                            ->title('Campaña en cola')
                            ->success()
                            ->body("Se procesará el envío a {$audienceCount} destinatarios en breve.")
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn (Campaign $record) => ! $record->sent_at),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Marketing PRO',
                'heading' => 'Todavía no has creado campañas',
                'description' => 'Prepara mensajes para seguidores, prospectos o clientes desde aquí. Cuando guardes la primera campaña, aparecerá en esta tabla.',
                'buttonLabel' => 'Crear campaña',
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

    private static function calculateAudienceCount(Tenant $tenant, ?AudienceType $audienceType): int
    {
        return match ($audienceType) {
            AudienceType::FOLLOWERS, null => $tenant->followers()->count(),
            AudienceType::LEADS => DB::table('leads')
                ->where('tenant_id', (string) $tenant->getKey())
                ->join('users', 'leads.email', '=', 'users.email')
                ->distinct()
                ->count('users.id'),
            AudienceType::ALL_CUSTOMERS => DB::table('appointments')
                ->where('tenant_id', (string) $tenant->getKey())
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id'),
            AudienceType::WAITLIST => DB::table('waitlists')
                ->where('tenant_id', (string) $tenant->getKey())
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id'),
        };
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
