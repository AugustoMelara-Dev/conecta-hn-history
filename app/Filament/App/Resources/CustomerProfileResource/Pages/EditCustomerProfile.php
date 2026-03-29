<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\Pages;

use App\Application\TenantGovernance\CustomerProfiles\Commands\MergeCustomerProfilesCommand;
use App\Application\TenantGovernance\CustomerProfiles\Handlers\MergeCustomerProfilesHandler;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use App\Infrastructure\TenantGovernance\CustomerProfile\TenantCustomerProfileEditReader;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class EditCustomerProfile extends EditTenantRecord
{
    protected static string $resource = CustomerProfileResource::class;

    public function getSubheading(): string
    {
        return $this->editReader()->subheading();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->currentProfile();

        if (! $profile instanceof CustomerProfile) {
            return $data;
        }

        return [
            ...$data,
            ...$this->editReader()->formState($profile),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mergeDuplicate')
                ->label('Fusionar duplicado')
                ->icon('heroicon-o-squares-plus')
                ->color('warning')
                ->form([
                    Select::make('duplicate_profile_id')
                        ->label('Perfil duplicado')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search): array => $this->duplicateOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => $this->duplicateLabel($value)),
                ])
                ->action(function (array $data): void {
                    $profile = $this->currentProfile();

                    if (! $profile instanceof CustomerProfile) {
                        return;
                    }

                    $duplicate = $this->profileRepository()->findTenantRecord(
                        (string) $profile->tenant_id,
                        (int) $data['duplicate_profile_id'],
                    );

                    if ($duplicate === null) {
                        Notification::make()
                            ->title('No se encontró el duplicado')
                            ->danger()
                            ->body('El perfil seleccionado ya no está disponible dentro de este negocio.')
                            ->send();

                        return;
                    }

                    $merged = app(MergeCustomerProfilesHandler::class)->handle(
                        new MergeCustomerProfilesCommand(
                            tenantId: (string) $profile->tenant_id,
                            primaryProfileId: (int) $profile->getKey(),
                            duplicateProfileId: (int) $duplicate->getKey(),
                            actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
                        ),
                    );

                    if ($merged === null) {
                        Notification::make()
                            ->title('No se pudo fusionar')
                            ->danger()
                            ->body('El perfil duplicado ya no esta disponible dentro de este negocio.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Perfiles fusionados')
                        ->success()
                        ->body('La ficha ahora concentra historial, tareas, conversaciones y fidelidad en un solo cliente.')
                        ->send();

                    $tenant = Filament::getTenant();

                    $this->redirect(CustomerProfileResource::getUrl('edit', [
                        'record' => $merged,
                        'tenant' => $tenant instanceof Tenant ? $tenant->slug : null,
                    ]));
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function duplicateOptions(string $search): array
    {
        $profile = $this->currentProfile();

        return $profile instanceof CustomerProfile
            ? $this->editReader()->duplicateOptions($profile, $search)
            : [];
    }

    private function duplicateLabel(mixed $value): ?string
    {
        $profile = $this->currentProfile();

        return $profile instanceof CustomerProfile
            ? $this->editReader()->duplicateLabel($profile, $value)
            : null;
    }

    private function profileRepository(): CustomerProfileRepositoryInterface
    {
        return app(CustomerProfileRepositoryInterface::class);
    }

    private function editReader(): TenantCustomerProfileEditReader
    {
        return app(TenantCustomerProfileEditReader::class);
    }

    private function currentProfile(): ?CustomerProfile
    {
        $record = $this->getRecord();

        return $record instanceof CustomerProfile ? $record : null;
    }
}
