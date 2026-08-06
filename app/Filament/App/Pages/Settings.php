<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Ajustes';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $title = 'Ajustes generales';

    protected static ?string $modelLabel = 'ajustes';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.app.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Preferencias generales')
                    ->description('Configuración básica de tu tienda.')
                    ->components([
                        Select::make('locale')
                            ->label('Idioma')
                            ->options([
                                'es' => 'Español',
                                'en' => 'English',
                            ])
                            ->default('es'),

                        Select::make('timezone')
                            ->label('Zona horaria')
                            ->options([
                                'America/Tegucigalpa' => 'Tegucigalpa (GMT-6)',
                                'America/Mexico_City' => 'Ciudad de México (GMT-6)',
                                'America/Bogota' => 'Bogotá (GMT-5)',
                            ])
                            ->default('America/Tegucigalpa'),

                        Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'HNL' => 'Lempira (HNL)',
                                'USD' => 'Dólar (USD)',
                                'MXN' => 'Peso mexicano (MXN)',
                                'COP' => 'Peso colombiano (COP)',
                            ])
                            ->default('HNL'),
                    ]),

                Section::make('Notificaciones')
                    ->components([
                        Toggle::make('email_notifications')
                            ->label('Notificaciones por email')
                            ->default(true),

                        Toggle::make('sms_notifications')
                            ->label('Notificaciones por SMS')
                            ->default(false),

                        Toggle::make('whatsapp_notifications')
                            ->label('Notificaciones por WhatsApp')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        // Persist settings logic here
        $this->notify('success', 'Ajustes guardados correctamente.');
    }
}
