<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\Pages;

use App\Application\TenantGovernance\CustomerProfiles\Commands\RunCustomerReactivationSweepCommand;
use App\Application\TenantGovernance\CustomerProfiles\Commands\SyncTenantCustomersCommand;
use App\Application\TenantGovernance\CustomerProfiles\Handlers\RunCustomerReactivationSweepHandler;
use App\Application\TenantGovernance\CustomerProfiles\Handlers\SyncTenantCustomersHandler;
use App\Filament\App\Pages\CustomerInbox;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class ListCustomerProfiles extends ListTenantRecords
{
    protected static string $resource = CustomerProfileResource::class;

    public function mount(): void
    {
        parent::mount();

        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantId = (string) $tenant->getKey();
        $profilesExist = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $profilesExist && $this->hasAnyCustomerActivity($tenantId)) {
            app(SyncTenantCustomersHandler::class)->handle(
                new SyncTenantCustomersCommand((string) $tenant->getKey()),
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCustomers')
                ->label('Sincronizar CRM')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $tenant = Filament::getTenant();

                    if (! $tenant instanceof Tenant) {
                        return;
                    }

                    app(SyncTenantCustomersHandler::class)->handle(
                        new SyncTenantCustomersCommand((string) $tenant->getKey()),
                    );

                    Notification::make()
                        ->success()
                        ->title('CRM actualizado')
                        ->body('Pedidos, citas y prospectos ya quedaron consolidados en fichas de cliente.')
                        ->send();
                }),
            Action::make('reactivationBatch')
                ->label('Lanzar reactivacion')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Lanzar lote de reactivacion')
                ->modalDescription('Envias correos de reactivacion al pool mas caliente del CRM y cada entrega queda auditada.')
                ->action(function (): void {
                    $tenant = Filament::getTenant();

                    if (! $tenant instanceof Tenant) {
                        return;
                    }

                    $result = app(RunCustomerReactivationSweepHandler::class)->handle(
                        new RunCustomerReactivationSweepCommand(
                            tenantId: (string) $tenant->getKey(),
                            actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
                            limit: 25,
                        ),
                    );

                    Notification::make()
                        ->success()
                        ->title('Lote procesado')
                        ->body("Procesados: {$result['processed']} | enviados: {$result['sent']} | omitidos: {$result['skipped']} | fallidos: {$result['failed']}.")
                        ->send();
                }),
            Action::make('touchpoints')
                ->label('Ver contactos')
                ->icon('heroicon-o-envelope')
                ->url(function (): string {
                    $tenant = Filament::getTenant();

                    return $tenant instanceof Tenant
                        ? CustomerTouchpointResource::getUrl('index', ['tenant' => $tenant->slug])
                        : '#';
                }),
            Action::make('inbox')
                ->label('Bandeja')
                ->icon('heroicon-o-inbox-stack')
                ->url(function (): string {
                    $tenant = Filament::getTenant();

                    return $tenant instanceof Tenant
                        ? CustomerInbox::getUrl(['tenant' => $tenant->slug])
                        : '#';
                }),
            Action::make('growthCenter')
                ->label('Ir a crecimiento')
                ->icon('heroicon-o-chart-bar-square')
                ->url(function (): string {
                    $tenant = Filament::getTenant();

                    return $tenant instanceof Tenant
                        ? route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false)
                        : '#';
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'CRM de clientes';
    }

    public function getSubheading(): string
    {
        return 'Ficha unificada con historial, segmentos y senales de reactivacion.';
    }

    private function hasAnyCustomerActivity(string $tenantId): bool
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
            })
            ->exists()
            || Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
                })
                ->exists()
            || Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('email')->orWhereNotNull('phone');
                })
                ->exists();
    }
}
