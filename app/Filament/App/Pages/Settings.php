<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class Settings extends Page
{
    protected static string $view = 'filament.app.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            \Filament\Facades\Filament::getTenant()->attributesToArray()
        );
    }

    public function form(Form $form): Form
    {
        $generalFields = [
            TextInput::make('name')
                ->label('Nombre del negocio')
                ->required(),
            TextInput::make('whatsapp_number')
                ->label('Numero de WhatsApp')
                ->required(),
        ];

        if (Schema::hasColumn('tenants', 'address')) {
            $generalFields[] = Textarea::make('address')
                ->label('Direccion');
        }

        return $form
            ->schema([
                Section::make('Configuracion general')
                    ->schema($generalFields),
                Section::make('Personalizacion de WhatsApp')
                    ->description('Usa {business_name}, {product_name} y {product_price} como variables.')
                    ->schema([
                        Textarea::make('whatsapp_template')
                            ->label('Plantilla de mensaje')
                            ->rows(3)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        \Filament\Facades\Filament::getTenant()->update($data);

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
}
