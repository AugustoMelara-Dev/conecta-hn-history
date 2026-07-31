<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Conversation;

use App\Application\Ai\Conversation\PublicConversationSubject;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class EncryptedPublicConversationSubjectResolver
{
    public const string COOKIE_NAME = 'dovra_ai_subject';

    public function __construct(private Encrypter $encrypter) {}

    public function resolve(?string $protectedCookie): PublicConversationSubject
    {
        $token = $this->decrypt($protectedCookie);
        $replacementCookie = null;

        if ($token === null) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $replacementCookie = $this->encrypter->encryptString($token);
        }

        return new PublicConversationSubject(
            actorId: hash_hmac(
                'sha256',
                'dovra-public-conversation-subject-v1|'.$token,
                $this->hmacKey(),
            ),
            replacementCookie: $replacementCookie,
        );
    }

    public function responseCookie(string $protectedToken): Cookie
    {
        return Cookie::create(
            name: self::COOKIE_NAME,
            value: $protectedToken,
            expire: now()->addYear(),
            path: '/',
            domain: null,
            secure: app()->environment('production') || (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    private function decrypt(?string $protectedCookie): ?string
    {
        if ($protectedCookie === null || $protectedCookie === '' || strlen($protectedCookie) > 4096) {
            return null;
        }

        try {
            $token = $this->encrypter->decryptString($protectedCookie);
        } catch (DecryptException) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1 ? $token : null;
    }

    private function hmacKey(): string
    {
        return hash_hmac(
            'sha256',
            'dovra-public-conversation-subject-hmac-key-v1',
            $this->encrypter->getKey(),
            true,
        );
    }
}
