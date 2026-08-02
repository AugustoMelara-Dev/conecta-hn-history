<?php

declare(strict_types=1);

return [
    'fakes' => [
        'web_chat' => ['enabled' => true, 'provider' => 'web_chat.fake'],
        'widget' => ['enabled' => true, 'provider' => 'widget.fake'],
        'email' => ['enabled' => true, 'provider' => 'email.fake'],
    ],
    'ports' => [
        'whatsapp' => ['provider' => 'whatsapp.fail_closed'],
        'sms' => ['provider' => 'sms.fail_closed'],
        'voice' => ['provider' => 'voice.fail_closed'],
    ],
];
