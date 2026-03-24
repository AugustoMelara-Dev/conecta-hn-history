<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Filament\App\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Historial de citas';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Appointment::STATUS_PENDING => 'Pendiente',
                        Appointment::STATUS_CONFIRMED => 'Confirmada',
                        Appointment::STATUS_COMPLETED => 'Completada',
                        Appointment::STATUS_CANCELLED => 'Cancelada',
                        Appointment::STATUS_NO_SHOW => 'No asistio',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Agenda')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Staff')
                    ->toggleable(),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (Appointment $record): string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Tenant
                            ? AppointmentResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $record])
                            : '#';
                    }),
            ])
            ->bulkActions([]);
    }
}
