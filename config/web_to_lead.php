<?php

declare(strict_types=1);

return [
    'captcha' => [
        'site_key' => env('WEB_TO_LEAD_CAPTCHA_SITE_KEY'),
        'secret' => env('WEB_TO_LEAD_CAPTCHA_SECRET'),
        'verify_url' => env('WEB_TO_LEAD_CAPTCHA_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],
];
