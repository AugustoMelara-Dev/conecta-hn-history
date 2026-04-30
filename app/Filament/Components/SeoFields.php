<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SeoFields
{
    public static function make(string $imageDirectory = 'seo'): Section
    {
        return Section::make('Optimización SEO')
            ->description('Configura cómo aparece esta página en Google y redes sociales.')
            ->icon('heroicon-o-magnifying-glass')
            ->collapsed()
            ->schema([
                TextInput::make('seo_title')
                    ->label('Título SEO')
                    ->placeholder('Título optimizado para Google (max. 60 caracteres)')
                    ->maxLength(70)
                    ->live()
                    ->helperText(fn (?string $state): string => self::getCharacterCountHelper($state, 60, 'Zona segura: 60 caracteres'))
                    ->hint(fn (?string $state): string => self::getCharacterCountBadge($state, 60)),
                Textarea::make('seo_description')
                    ->label('Meta descripción')
                    ->placeholder('Descripción breve para resultados de búsqueda (max. 160 caracteres)')
                    ->rows(3)
                    ->maxLength(170)
                    ->live()
                    ->helperText(fn (?string $state): string => self::getCharacterCountHelper($state, 160, 'Zona segura: 160 caracteres'))
                    ->hint(fn (?string $state): string => self::getCharacterCountBadge($state, 160)),
                TagsInput::make('seo_keywords')
                    ->label('Palabras clave')
                    ->placeholder('Presiona Enter para agregar')
                    ->separator(',')
                    ->helperText('Palabras clave separadas por comas.'),
                FileUpload::make('og_image')
                    ->label('Imagen para redes sociales')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->image()
                    ->disk('public')
                    ->directory($imageDirectory)
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('1200:630')
                    ->imageResizeTargetWidth('1200')
                    ->imageResizeTargetHeight('630')
                    ->helperText('Tamaño recomendado: 1200x630px.'),
            ]);
    }

    private static function getCharacterCountHelper(?string $state, int $limit, string $label): string
    {
        $count = $state ? strlen($state) : 0;
        $remaining = $limit - $count;

        if ($remaining < 0) {
            return 'Excedido por '.abs($remaining)." caracteres. {$label}";
        }

        return "{$label} | {$remaining} caracteres disponibles";
    }

    private static function getCharacterCountBadge(?string $state, int $limit): string
    {
        $count = $state ? strlen($state) : 0;

        if ($count > $limit) {
            return "ALERTA {$count}/{$limit}";
        }

        if ($count > ($limit * 0.9)) {
            return "CASI {$count}/{$limit}";
        }

        return "{$count}/{$limit}";
    }
}
