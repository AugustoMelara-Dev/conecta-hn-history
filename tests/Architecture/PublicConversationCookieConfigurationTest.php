<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Tests\TestCase;

final class PublicConversationCookieConfigurationTest extends TestCase
{
    public function test_public_subject_is_exempt_while_session_and_xsrf_remain_encrypted(): void
    {
        /** @var EncryptCookies $middleware */
        $middleware = app(EncryptCookies::class);

        self::assertTrue($middleware->isDisabled('dovra_ai_subject'));
        self::assertFalse($middleware->isDisabled((string) config('session.cookie')));
        self::assertFalse($middleware->isDisabled('XSRF-TOKEN'));
    }
}
