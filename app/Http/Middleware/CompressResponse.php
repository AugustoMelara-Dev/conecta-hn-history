<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CompressResponse - Middleware que comprime responses con gzip.
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        $compressed = gzencode($content, 6);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (! str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'text/html')
            || str_contains($contentType, 'text/css')
            || str_contains($contentType, 'application/javascript')
            || str_contains($contentType, 'application/json');
    }
}
