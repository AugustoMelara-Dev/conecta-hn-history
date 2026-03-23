<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.app.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof \App\Models\User || ! $tenant instanceof Tenant) {
            return false;
        }

        return app(\App\Application\Access\Contracts\TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            \App\Domain\TenantGovernance\Enums\Capability::CATALOG_BASIC,
            \App\Domain\Access\Enums\Permission::SETTINGS_VIEW
        );
    }

    public static function getNavigationLabel(): string
    {
        return 'Configuracion';
    }

    public function getTitle(): string
    {
        return 'Configuracion del negocio';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $attributes = $tenant->getAttributes();

        $this->form->fill([
            'name' => (string) ($attributes['name'] ?? ''),
            'whatsapp_number' => (string) ($attributes['whatsapp_number'] ?? ''),
            'description' => (string) ($attributes['description'] ?? ''),
            'address' => (string) ($attributes['address'] ?? ''),
            'whatsapp_message' => (string) ($attributes['whatsapp_message'] ?? ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informacion base')
                    ->description('Estos datos se usan en el panel y en la pagina publica del negocio.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del negocio')
                            ->required(),
                        TextInput::make('whatsapp_number')
                            ->label('Numero de WhatsApp')
                            ->required(),
                        Textarea::make('description')
                            ->label('Descripcion corta')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('address')
                            ->label('Direccion')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Mensaje base de WhatsApp')
                    ->description('Puedes usar {business_name}, {product_name} y {product_price} como variables.')
                    ->schema([
                        Textarea::make('whatsapp_message')
                            ->label('Mensaje predeterminado')
                            ->rows(4)
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
                ->title('No se pudo guardar la configuracion')
                ->send();

            return;
        }

        $tenant->update([
            'name' => (string) ($data['name'] ?? ''),
            'whatsapp_number' => (string) ($data['whatsapp_number'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'whatsapp_message' => (string) ($data['whatsapp_message'] ?? ''),
        ]);

        Notification::make()
            ->success()
            ->title('Configuracion guardada')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->submit('save'),
        ];
    }

    protected function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
