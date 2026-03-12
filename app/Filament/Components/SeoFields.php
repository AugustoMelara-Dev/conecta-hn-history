<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * SeoFields - Schema Reutilizable para SEO
 *
 * Componente inyectable en cualquier formulario de Filament
 * para gestionar campos SEO estandarizados.
 *
 * Uso:
 * use App\Filament\Components\SeoFields;
 *
 * public static function form(Schema $schema): Schema {
 *     return $schema->schema([
 *         // ...otros campos
 *         SeoFields::make(),
 *     ]);
 * }
 */
class SeoFields
{
    /**
     * Genera el schema completo de campos SEO
     */
    public static function make(string $imageDirectory = 'seo'): Section
    {
        return Section::make('Optimización SEO')
            ->description('Configura cómo aparece esta página en Google y redes sociales')
            ->icon('heroicon-o-magnifying-glass')
            ->collapsed()
            ->schema([
                // ═══════════════════════════════════════════════════════════
                // CAMPO: Título SEO (60 caracteres recomendados)
                // ═══════════════════════════════════════════════════════════
                TextInput::make('seo_title')
                    ->label('Título SEO')
                    ->placeholder('Título optimizado para Google (máx. 60 caracteres)')
                    ->maxLength(70)
                    ->live()
                    ->helperText(fn ($state) => self::getCharacterCountHelper($state, 60, 'Zona segura: 60 caracteres'))
                    ->hint(fn ($state) => self::getCharacterCountBadge($state, 60)),

                // ═══════════════════════════════════════════════════════════
                // CAMPO: Meta Descripción (160 caracteres recomendados)
                // ═══════════════════════════════════════════════════════════
                Textarea::make('seo_description')
                    ->label('Meta Descripción')
                    ->placeholder('Descripción breve para resultados de búsqueda (máx. 160 caracteres)')
                    ->rows(3)
                    ->maxLength(170)
                    ->live()
                    ->helperText(fn ($state) => self::getCharacterCountHelper($state, 160, 'Zona segura: 160 caracteres'))
                    ->hint(fn ($state) => self::getCharacterCountBadge($state, 160)),

                // ═══════════════════════════════════════════════════════════
                // CAMPO: Palabras Clave
                // ═══════════════════════════════════════════════════════════
                TagsInput::make('seo_keywords')
                    ->label('Palabras Clave')
                    ->placeholder('Presiona Enter para agregar')
                    ->separator(',')
                    ->helperText('Palabras clave separadas por comas (ej: restaurante, comida, tegucigalpa)'),

                // ═══════════════════════════════════════════════════════════
                // CAMPO: Imagen Open Graph (1200x630px recomendados)
                // ═══════════════════════════════════════════════════════════
                FileUpload::make('og_image')
                    ->label('Imagen para Redes Sociales')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->image()
                    ->disk('public')
                    ->directory($imageDirectory)
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('1200:630')
                    ->imageResizeTargetWidth(1200)
                    ->imageResizeTargetHeight(630)
                    ->helperText('Tamaño recomendado: 1200x630px. Esta imagen aparecerá al compartir en WhatsApp, Facebook y Twitter.'),
            ]);
    }

    /**
     * Genera el helper text con contador de caracteres
     */
    private static function getCharacterCountHelper(?string $state, int $limit, string $label): string
    {
        $count = $state ? strlen($state) : 0;
        $remaining = $limit - $count;

        if ($remaining < 0) {
            return "⚠️ {$label} | Excedido por ".abs($remaining).' caracteres';
        }

        return "{$label} | {$remaining} caracteres disponibles";
    }

    /**
     * Genera el badge visual de contador
     */
    private static function getCharacterCountBadge(?string $state, int $limit): string
    {
        $count = $state ? strlen($state) : 0;

        if ($count > $limit) {
            return "⚠️ {$count}/{$limit}";
        }

        if ($count > $limit * 0.9) {
            return "⚡ {$count}/{$limit}";
        }

        return "{$count}/{$limit}";
    }
}
