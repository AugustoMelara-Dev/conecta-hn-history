<?php

namespace App\Filament\App\Pages;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
use Filament\Schemas\Schema;

class EditTenantProfile extends BaseEditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Perfil de Negocio';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información Básica')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Negocio')
                            ->required(),

                        TextInput::make('slug')
                            ->label('URL (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true),

                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('tenants/logos')
                            ->imageEditor(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3),

                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel(),

                        TextInput::make('address')
                            ->label('Dirección'),

                        TextInput::make('website')
                            ->label('Sitio Web')
                            ->url(),
                    ])->columns(2),

                Section::make('Personalización (PRO)')
                    ->description('Personaliza la apariencia de tu micrositio. Exclusivo para planes PRO.')
                    ->schema([
                        FileUpload::make('banner_path')
                            ->label('Banner de Portada')
                            ->image()
                            ->directory('tenants/banners')
                            ->disabled(fn () => ! $this->tenant->is_pro)
                            ->helperText(fn () => ! $this->tenant->is_pro ? 'Actualiza a PRO para subir un banner.' : null),

                        ColorPicker::make('primary_color')
                            ->label('Color de Marca')
                            ->disabled(fn () => ! $this->tenant->is_pro)
                            ->helperText(fn () => ! $this->tenant->is_pro ? 'Actualiza a PRO para personalizar tu color.' : null),
                    ])->columns(2),
            ]);
    }
}
