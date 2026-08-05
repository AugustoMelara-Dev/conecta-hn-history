<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Identity\TenantInvitationService;
use App\Filament\App\Resources\TenantInvitationResource\Pages;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class TenantInvitationResource extends Resource
{
    protected static ?string $model = TenantInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 51;

    public static function getNavigationLabel(): string
    {
        return 'Invitaciones';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function getModelLabel(): string
    {
        return 'invitación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'invitaciones';
    }

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        return $tenant !== null
            && $user instanceof User
            && app(TenantInvitationService::class)->canManageTeam(
                (string) $tenant->getKey(),
                (string) $user->getKey(),
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('tenant_id', Filament::getTenant()->getKey())
                ->with('inviter'))
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge(),
                Tables\Columns\TextColumn::make('inviter.name')
                    ->label('Invitado por')
                    ->placeholder('Sistema'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vence')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lifecycle')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (TenantInvitation $record): string => self::lifecycle($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Activa' => 'warning',
                        'Aceptada' => 'success',
                        'Revocada' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reissue')
                    ->label('Reenviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (TenantInvitation $record): bool => $record->isPending())
                    ->action(function (TenantInvitation $record): void {
                        $actor = auth()->user();
                        if (! $actor instanceof User) {
                            throw new LogicException('An authenticated user is required to reissue an invitation.');
                        }

                        $issued = app(TenantInvitationService::class)->reissue(
                            (string) $record->getKey(),
                            (string) $actor->getKey(),
                        );
                        Notification::route('mail', $issued->email)->notify(
                            new TenantInvitationNotification(
                                tenantName: (string) Filament::getTenant()->name,
                                inviterName: (string) $actor->name,
                                role: (string) $record->role,
                                plainTextToken: $issued->plainTextToken,
                                expiresAt: $issued->expiresAt,
                            ),
                        );

                        FilamentNotification::make()
                            ->title('Invitación reemplazada')
                            ->body('El enlace anterior quedó revocado y el nuevo correo se despachó mediante la cola configurada.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label('Revocar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TenantInvitation $record): bool => $record->isPending())
                    ->action(function (TenantInvitation $record): void {
                        $actor = auth()->user();
                        if (! $actor instanceof User) {
                            throw new LogicException('An authenticated user is required to revoke an invitation.');
                        }

                        app(TenantInvitationService::class)->revoke(
                            (string) $record->getKey(),
                            (string) $actor->getKey(),
                        );

                        FilamentNotification::make()
                            ->title('Invitación revocada')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay invitaciones')
            ->emptyStateDescription('Las invitaciones creadas desde Equipo aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantInvitations::route('/'),
        ];
    }

    private static function lifecycle(TenantInvitation $invitation): string
    {
        if ($invitation->accepted_at !== null) {
            return 'Aceptada';
        }

        if ($invitation->revoked_at !== null) {
            return 'Revocada';
        }

        return $invitation->expires_at?->isPast() === true ? 'Vencida' : 'Activa';
    }
}
