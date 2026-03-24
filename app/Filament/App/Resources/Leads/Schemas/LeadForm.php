<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos del prospecto')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->maxLength(255)
                            ->placeholder('Ej: Maria Lopez'),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('cliente@correo.com'),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('9999-9999'),
                        Select::make('product_id')
                            ->label('Producto relacionado')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Consulta general'),
                        Select::make('source')
                            ->label('Origen')
                            ->options([
                                'manual' => 'Manual',
                                'website' => 'Sitio web',
                                'catalog' => 'Catálogo',
                                'whatsapp_click' => 'WhatsApp',
                            ])
                            ->default('manual')
                            ->required(),
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'new' => 'Nuevo',
                                'contacted' => 'Contactado',
                                'closed' => 'Cerrado',
                                'lost' => 'Perdido',
                            ])
                            ->default('new')
                            ->required(),
                        Textarea::make('message')
                            ->label('Notas o mensaje')
                            ->rows(4)
                            ->columnSpanFull()
                            ->placeholder('Resumen de la consulta o seguimiento.'),
                    ])
                    ->columns(2),
            ]);
    }
}
