<?php

namespace App\Services;

use App\Models\Tenant;
use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use Illuminate\Support\Facades\Cache;

/**
 * QrCodeService - Generador de Códigos QR
 * 
 * Utiliza chillerlan/php-qrcode (compatible con Laravel 12)
 * Genera códigos QR en formato SVG para máxima calidad de impresión
 */
class QrCodeService
{
    /**
     * Duración del caché en días
     */
    protected const CACHE_DAYS = 7;

    /**
     * Genera o recupera del caché el código QR para un tenant
     */
    public function getQrCode(Tenant $tenant, int $size = 300): string
    {
        $cacheKey = $this->getCacheKey($tenant);
        
        return Cache::remember(
            $cacheKey, 
            now()->addDays(self::CACHE_DAYS), 
            fn() => $this->generateQrCode($tenant, $size)
        );
    }

    /**
     * Genera el código QR en formato SVG
     * SVG es ideal porque:
     * - Es ligero (texto vs binario)
     * - Es escalable sin pérdida de calidad
     * - Se puede estilizar con CSS
     */
    protected function generateQrCode(Tenant $tenant, int $size): string
    {
        try {
            $url = url('/' . $tenant->slug);
            
            $options = new QROptions([
                'outputType'         => QROutputInterface::MARKUP_SVG,
                'eccLevel'           => QRCode::ECC_H, // Alta corrección de errores
                'svgViewBoxSize'     => $size,
                'addQuietzone'       => true,
                'quietzoneSize'      => 2,
                'drawLightModules'   => false,
                'connectPaths'       => true,
                'drawCircularModules' => false,
                'keepAsSquare'       => [
                    QRMatrix::M_FINDER_DARK,
                    QRMatrix::M_FINDER_DOT,
                    QRMatrix::M_ALIGNMENT_DARK,
                ],
            ]);

            return (new QRCode($options))->render($url);
        } catch (\Exception $e) {
            // Fallback: retornar un placeholder SVG si hay error
            return $this->getFallbackSvg($size);
        }
    }

    /**
     * Genera la clave de caché única para el QR del tenant
     */
    protected function getCacheKey(Tenant $tenant): string
    {
        return sprintf(
            'qr_tenant_%d_v%d',
            $tenant->id,
            $tenant->updated_at->timestamp
        );
    }

    /**
     * Invalida el caché del QR de un tenant
     */
    public function invalidateCache(Tenant $tenant): void
    {
        Cache::forget($this->getCacheKey($tenant));
    }

    /**
     * Genera un QR para poster (tamaño grande para impresión)
     */
    public function getQrCodeForPoster(Tenant $tenant): string
    {
        $cacheKey = $this->getCacheKey($tenant) . '_poster';
        
        return Cache::remember(
            $cacheKey,
            now()->addDays(self::CACHE_DAYS),
            fn() => $this->generateQrCode($tenant, 500)
        );
    }

    /**
     * Retorna el QR como Data URI para incrustar directamente en HTML
     */
    public function getQrCodeAsDataUri(Tenant $tenant, int $size = 300): string
    {
        $svg = $this->getQrCode($tenant, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * SVG de fallback si falla la generación
     */
    protected function getFallbackSvg(int $size): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="$size" height="$size" viewBox="0 0 $size $size">
    <rect width="100%" height="100%" fill="#f3f4f6"/>
    <text x="50%" y="50%" text-anchor="middle" fill="#9ca3af" font-size="16" dy=".3em">QR Code</text>
</svg>
SVG;
    }
}

