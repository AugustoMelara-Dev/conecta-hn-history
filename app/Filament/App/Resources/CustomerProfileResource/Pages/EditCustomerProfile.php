<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\Pages;

use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Domain\TenantGovernance\Services\CustomerProfileWorkspaceReader;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
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
        return 'Ajusta notas, opt-ins, etiquetas, tareas y continuidad con una lectura 360 del cliente.';
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
                    $duplicate = CustomerProfile::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', (string) $this->getRecord()->tenant_id)
                        ->findOrFail((int) $data['duplicate_profile_id']);

                    $merged = app(CustomerProfileManager::class)->mergeProfiles(
                        primary: $this->getRecord(),
                        duplicate: $duplicate,
                        actorUserId: auth()->id(),
                    );

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
        return app(CustomerProfileWorkspaceReader::class)->duplicateOptions($this->getRecord(), $search);
    }

    private function duplicateLabel(mixed $value): ?string
    {
        return app(CustomerProfileWorkspaceReader::class)->duplicateLabel($this->getRecord(), $value);
    }

    private function formatDuplicateLabel(CustomerProfile $profile): string
    {
        return trim(implode(' | ', array_filter([
            '#'.$profile->getKey(),
            $profile->name,
            $profile->email,
            $profile->phone,
            $profile->last_seen_at?->diffForHumans(),
        ])));
    }
}
