<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $user instanceof \App\Models\User || ! $tenant instanceof \App\Models\Tenant) {
            return false;
        }

        return app(\App\Application\Access\Contracts\TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            \App\Domain\TenantGovernance\Enums\Capability::CATALOG_BASIC,
            \App\Domain\Access\Enums\Permission::SETTINGS_VIEW
        );
    }

    // Navigation Icon removed to fix type error

    protected string $view = 'filament.app.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->form->fill($tenant->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Configuración General')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Negocio')
                            ->required(),
                        TextInput::make('whatsapp_number')
                            ->label('Número de WhatsApp')
                            ->required(),
                        Textarea::make('address')
                            ->label('Dirección'),
                    ]),
                Section::make('Personalización de WhatsApp')
                    ->description('Usa {business_name}, {product_name} y {product_price} como variables.')
                    ->schema([
                        Textarea::make('whatsapp_template')
                            ->label('Plantilla de Mensaje')
                            ->rows(3)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar la configuración')
                ->send();

            return;
        }

        $tenant->update($data);

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Cambios')
                ->submit('save'),
        ];
    }

    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
