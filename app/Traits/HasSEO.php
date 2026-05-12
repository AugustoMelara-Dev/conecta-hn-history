<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * HasSEO Trait - Funcionalidad SEO Reutilizable
 *
 * Proporciona métodos helper para gestión de metadatos SEO
 * en cualquier modelo Eloquent.
 *
 * Uso:
 * class Tenant extends Model {
 *     use HasSEO;
 * }
 *
 * // En Blade:
 * $tenant->getSeoTitle()
 * $tenant->getSeoDescription()
 * $tenant->getOgImage()
 */
trait HasSEO
{
    /**
     * Obtiene el título SEO con fallback automático
     *
     * Cascada:
     * 1. seo_title personalizado
     * 2. "{name} | Vito Business OS"
     */
    public function getSeoTitle(): string
    {
        if (! empty($this->seo_title)) {
            return $this->seo_title;
        }

        $name = $this->name ?? $this->title ?? 'Página';

        return "{$name} | Vito Business OS";
    }

    /**
     * Obtiene la meta descripción con fallback automático
     *
     * Cascada:
     * 1. seo_description personalizado
     * 2. description del modelo (truncado a 160 chars)
     * 3. Mensaje genérico
     */
    public function getSeoDescription(): string
    {
        if (! empty($this->seo_description)) {
            return $this->seo_description;
        }

        if (! empty($this->description)) {
            return \Illuminate\Support\Str::limit(strip_tags($this->description), 160);
        }

        $name = $this->name ?? $this->title ?? 'esta página';

        return "Descubre {$name} en Vito Business OS - Directorio y plataforma para negocios locales.";
    }

    /**
     * Obtiene las palabras clave como string
     */
    public function getSeoKeywords(): string
    {
        if (empty($this->seo_keywords)) {
            return '';
        }

        $keywords = is_array($this->seo_keywords)
            ? $this->seo_keywords
            : json_decode($this->seo_keywords, true);

        return is_array($keywords) ? implode(', ', $keywords) : '';
    }

    /**
     * Obtiene la URL completa de la imagen OG con fallback
     *
     * Cascada:
     * 1. og_image personalizada
     * 2. banner_path del modelo
     * 3. logo_path del modelo
     * 4. Imagen por defecto de la plataforma
     */
    public function getOgImage(): string
    {
        if (! empty($this->og_image)) {
            return asset('storage/'.$this->og_image);
        }

        if (! empty($this->banner_path)) {
            return asset('storage/'.$this->banner_path);
        }

        if (! empty($this->logo_path)) {
            return asset('storage/'.$this->logo_path);
        }

        if (! empty($this->hero_image)) {
            return asset('storage/'.$this->hero_image);
        }

        // Imagen por defecto de la plataforma
        return asset('images/og-default.png');
    }

    /**
     * Obtiene la URL canónica del modelo
     */
    public function getCanonicalUrl(): string
    {
        if (method_exists($this, 'getPublicUrl')) {
            return $this->getPublicUrl();
        }

        $slug = $this->slug ?? $this->id;
        $type = strtolower(class_basename($this));

        return url("/{$type}/{$slug}");
    }

    /**
     * Genera un array completo de metadatos SEO
     */
    public function getSeoMetadata(): array
    {
        return [
            'title' => $this->getSeoTitle(),
            'description' => $this->getSeoDescription(),
            'keywords' => $this->getSeoKeywords(),
            'og_image' => $this->getOgImage(),
            'canonical' => $this->getCanonicalUrl(),
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
        ];
    }
}
