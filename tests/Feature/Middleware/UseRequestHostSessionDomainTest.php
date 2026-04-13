<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UseRequestHostSessionDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/session-domain', static function () {
            return response()->json([
                'session_domain' => config('session.domain'),
            ]);
        });
    }

    #[Test]
    public function it_clears_session_domain_when_request_host_does_not_match_configured_domain(): void
    {
        config(['session.domain' => '.vito.hn']);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'vitobusinessos.vercel.app'])
            ->get('/__test/session-domain');

        $response
            ->assertOk()
            ->assertJson([
                'session_domain' => null,
            ]);
    }

    #[Test]
    public function it_keeps_session_domain_when_request_host_matches_configured_domain(): void
    {
        config(['session.domain' => '.vito.hn']);

        $response = $this->get('http://app.vito.hn/__test/session-domain');

        $response
            ->assertOk()
            ->assertJson([
                'session_domain' => '.vito.hn',
            ]);
    }
}
