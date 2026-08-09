<?php

declare(strict_types=1);

namespace App\Infrastructure\Crm;

use Illuminate\Support\Facades\Http;

final class HttpWebToLeadCaptchaVerifier
{
    public function verify(string $token, ?string $remoteIp): bool
    {
        $secret = (string) config('web_to_lead.captcha.secret');
        $endpoint = (string) config('web_to_lead.captcha.verify_url');
        if ($secret === '' || $endpoint === '' || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(5)
                ->post($endpoint, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));

            return $response->successful() && $response->json('success') === true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
