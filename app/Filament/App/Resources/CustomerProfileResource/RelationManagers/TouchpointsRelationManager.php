<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TouchpointsRelationManager extends RelationManager
{
    protected static string $relationship = 'touchpoints';

    protected static ?string $title = 'Historial de contactos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                        'phone' => 'Llamada',
                        default => str($state)->replace('_', ' ')->title()->value(),
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
                    }),
                Tables\Columns\TextColumn::make('delivery_status_label')
                    ->label('Entrega')
                    ->badge()
                    ->color(fn (CustomerTouchpoint $record): string => match ($record->delivery_status) {
                        CustomerTouchpoint::DELIVERY_DELIVERED => 'success',
                        CustomerTouchpoint::DELIVERY_OPENED => 'info',
                        CustomerTouchpoint::DELIVERY_CLICKED => 'primary',
                        CustomerTouchpoint::DELIVERY_REPLIED => 'success',
                        CustomerTouchpoint::DELIVERY_BOUNCED,
                        CustomerTouchpoint::DELIVERY_COMPLAINED => 'danger',
                        CustomerTouchpoint::DELIVERY_DELAYED => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('automation_key')
                    ->label('Playbook')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? str($state)->replace('_', ' ')->title()->value()
                        : 'Manual'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(38),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Action::make('open')
                    ->label('Ver cola')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (): string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Tenant
                            ? CustomerTouchpointResource::getUrl('index', ['tenant' => $tenant->slug])
                            : '#';
                    }),
            ])
            ->bulkActions([]);
    }
}
