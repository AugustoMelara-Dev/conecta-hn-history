<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Middleware\NormalizeInternalRedirectLocation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class NormalizeInternalRedirectLocationTest extends TestCase
{
    public function test_inertia_version_conflict_location_uses_forwarded_public_origin(): void
    {
        app()['env'] = 'production';

        try {
            $request = Request::create('https://vito-business-os-production.up.railway.app/', 'GET');
            $request->headers->set('X-Forwarded-Host', 'vitobusinessos.vercel.app');
            $request->headers->set('X-Forwarded-Proto', 'https');

            $response = (new NormalizeInternalRedirectLocation)->handle(
                $request,
                fn () => new Response('', 409, [
                    'X-Inertia-Location' => 'https://vito-business-os-production.up.railway.app',
                ])
            );
        } finally {
            app()['env'] = 'testing';
        }

        self::assertSame(
            'https://vitobusinessos.vercel.app/',
            $response->headers->get('X-Inertia-Location')
        );
    }

    public function test_inertia_version_conflict_preserves_path_query_and_fragment(): void
    {
        app()['env'] = 'production';

        try {
            $request = Request::create('https://vito-business-os-production.up.railway.app/login', 'GET');
            $request->headers->set('X-Forwarded-Host', 'vitobusinessos.vercel.app');
            $request->headers->set('X-Forwarded-Proto', 'https');

            $response = (new NormalizeInternalRedirectLocation)->handle(
                $request,
                fn () => new Response('', 409, [
                    'X-Inertia-Location' => 'https://vito-business-os-production.up.railway.app/login?expired=1#retry',
                ])
            );
        } finally {
            app()['env'] = 'testing';
        }

        self::assertSame(
            'https://vitobusinessos.vercel.app/login?expired=1#retry',
            $response->headers->get('X-Inertia-Location')
        );
    }
}
