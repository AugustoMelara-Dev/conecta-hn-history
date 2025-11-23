<?php

namespace App\Filament\App\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class Settings extends Page
{
    // Navigation Icon removed to fix type error

    protected string $view = 'filament.app.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            \Filament\Facades\Filament::getTenant()->attributesToArray()
        );
    }

    public function form(Form $form): Form
    {
        return $form
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
        
        \Filament\Facades\Filament::getTenant()->update($data);
        
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
}
