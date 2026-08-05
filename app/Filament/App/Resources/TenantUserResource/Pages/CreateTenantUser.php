<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Application\Identity\TenantInvitationService;
use App\Filament\App\Resources\TenantUserResource;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use LogicException;

class CreateTenantUser extends CreateRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to issue an invitation.');
        }

        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->getKey(),
            inviterUserId: (string) $actor->getKey(),
            email: (string) $data['email'],
            role: (string) $data['role'],
            presetKey: filled($data['preset_key'] ?? null) ? (string) $data['preset_key'] : null,
            grantedPermissions: array_values((array) ($data['granted_permissions'] ?? [])),
        );

        Notification::route('mail', $invitation->email)->notify(
            new TenantInvitationNotification(
                tenantName: (string) $tenant->name,
                inviterName: (string) $actor->name,
                role: (string) $data['role'],
                plainTextToken: $invitation->plainTextToken,
                expiresAt: $invitation->expiresAt,
            ),
        );

        FilamentNotification::make()
            ->title('Invitación creada')
            ->body('El correo de invitación se despachó mediante la cola configurada.')
            ->success()
            ->send();

        return TenantInvitation::query()->findOrFail($invitation->invitationId);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Invitar miembro al equipo';
    }
}
