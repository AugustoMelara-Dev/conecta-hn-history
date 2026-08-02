<?php

declare(strict_types=1);

return [
    'contract' => [
        'version' => 'program-07-v2',
        'retry' => [
            'max_attempts' => 3,
            'durable_states' => ['pending', 'provider_unavailable', 'delivered', 'blocked'],
            'retryable_states' => ['pending', 'provider_unavailable'],
            'backoff_seconds' => [60, 300, 900],
        ],
        'idempotency' => [
            'client_key_field' => 'idempotency_key',
            'provider_key_field' => 'provider_idempotency_key',
            'scope' => 'tenant:channel:connection',
            'exactly_once' => false,
            'provider_must_honor_key' => true,
        ],
        'webhook_verification' => [
            'algorithm' => 'hmac-sha256',
            'required_for' => ['email', 'whatsapp', 'sms', 'voice'],
            'fail_closed_without_signature' => true,
            'replay_claim_table' => 'channel_webhook_claims',
        ],
        'data_classification' => [
            'tenant_payload' => 'confidential',
            'channel_identity' => 'confidential',
            'provider_credentials' => 'secret_reference_only',
            'webhook_payload' => 'confidential',
        ],
        'retention' => [
            'delivery_outbox_days' => 30,
            'delivery_attempts_days' => 90,
            'webhook_claims_days' => 90,
            'provider_health_days' => 30,
            'deletion_marker_required' => true,
        ],
        'health' => [
            'table' => 'channel_provider_health',
            'states' => ['available', 'unavailable', 'stale'],
            'stale_after_seconds' => 300,
            'market_and_provider_scoped' => true,
        ],
    ],
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
