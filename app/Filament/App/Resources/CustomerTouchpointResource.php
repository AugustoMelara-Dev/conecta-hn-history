<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CustomerTouchpointResource\Pages;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerTouchpointResource extends Resource
{
    protected static ?string $model = CustomerTouchpoint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Contactos';
    }

    public static function getModelLabel(): string
    {
        return 'Contacto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Historial de contactos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereIn('status', [CustomerTouchpoint::STATUS_FAILED, CustomerTouchpoint::STATUS_SKIPPED])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customerProfile', 'author'])->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('customerProfile.name')
                    ->label('Cliente')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($nested) use ($search): void {
                            $nested
                                ->where('recipient_address', 'like', "%{$search}%")
                                ->orWhereHas('customerProfile', function ($customerQuery) use ($search): void {
                                    $customerQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->weight('bold')
                    ->description(fn (CustomerTouchpoint $record): string => (string) ($record->recipient_address ?? $record->customerProfile?->email ?? 'Sin destinatario')),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                        'phone' => 'Llamada',
                        default => str($state)->replace('_', ' ')->title()->value(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'email' => 'info',
                        'whatsapp' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        CustomerTouchpoint::STATUS_SENT => 'Enviado',
                        CustomerTouchpoint::STATUS_SKIPPED => 'Omitido',
                        CustomerTouchpoint::STATUS_FAILED => 'Fallido',
                        CustomerTouchpoint::STATUS_MANUAL => 'Manual',
                        default => 'En cola',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        CustomerTouchpoint::STATUS_SENT => 'success',
                        CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                        CustomerTouchpoint::STATUS_FAILED => 'danger',
                        CustomerTouchpoint::STATUS_MANUAL => 'gray',
                        default => 'info',
                    })
                    ->description(fn (CustomerTouchpoint $record): ?string => $record->last_error),
                Tables\Columns\TextColumn::make('delivery_status_label')
                    ->label('Entrega')
                    ->badge()
                    ->color(fn (CustomerTouchpoint $record): string => match ($record->delivery_status) {
                        CustomerTouchpoint::DELIVERY_DELIVERED => 'success',
                        CustomerTouchpoint::DELIVERY_OPENED => 'info',
                        CustomerTouchpoint::DELIVERY_CLICKED => 'primary',
                        CustomerTouchpoint::DELIVERY_READ => 'success',
                        CustomerTouchpoint::DELIVERY_REPLIED => 'success',
                        CustomerTouchpoint::DELIVERY_BOUNCED,
                        CustomerTouchpoint::DELIVERY_COMPLAINED,
                        CustomerTouchpoint::DELIVERY_FAILED => 'danger',
                        CustomerTouchpoint::DELIVERY_DELAYED => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('automation_key')
                    ->label('Playbook')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? str($state)->replace('_', ' ')->title()->value()
                        : 'Manual'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Responsable')
                    ->default('Sistema')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Proveedor')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? strtoupper((string) $state) : 'Interno')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Envio')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                        'phone' => 'Llamada',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        CustomerTouchpoint::STATUS_QUEUED => 'En cola',
                        CustomerTouchpoint::STATUS_SENT => 'Enviado',
                        CustomerTouchpoint::STATUS_SKIPPED => 'Omitido',
                        CustomerTouchpoint::STATUS_FAILED => 'Fallido',
                        CustomerTouchpoint::STATUS_MANUAL => 'Manual',
                    ]),
            ])
            ->actions([
                Action::make('customer')
                    ->label('Abrir cliente')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (CustomerTouchpoint $record): string {
                        $tenant = static::getTenantRecord();
                        $profile = $record->customerProfile;

                        return $tenant instanceof Tenant && $profile instanceof CustomerProfile
                            ? CustomerProfileResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $profile])
                            : '#';
                    }),
            ])
            ->bulkActions([])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Automatizacion',
                'heading' => 'Todavia no hay contactos registrados',
                'description' => 'Aqui apareceran los correos, WhatsApp y seguimientos que ejecutes desde el CRM del negocio.',
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerTouchpoints::route('/'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
