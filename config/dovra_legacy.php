<?php

declare(strict_types=1);

return [
    'allowed_references' => [
        [
            'path' => '.env.example',
            'classification' => 'operational_compatibility',
            'reason' => 'Telemetry and Horizon identifiers remain stable until deployed queues and dashboards have a verified dual-read migration.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'app/ValueObjects/AuditContext.php',
            'classification' => 'operational_compatibility',
            'reason' => 'The historical system actor identifier must remain correlatable across retained audit events until observability migration is certified.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'config/conecta.php',
            'classification' => 'operational_compatibility',
            'reason' => 'Legacy feature-flag keys remain readable until tenant governance moves them behind the Dovra compatibility adapter.',
            'removal_program' => 'Program 03',
        ],
        [
            'path' => 'config/observability.php',
            'classification' => 'operational_compatibility',
            'reason' => 'The default telemetry service name remains stable until collectors and dashboards support the Dovra identifier.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'resources/schemas/auth/otp_generated.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/auth/user_password_changed.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/auth/user_profile_updated.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/events/user.registered.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/otp.generated.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/user.password.changed.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
        [
            'path' => 'resources/schemas/user.registered.v1.json',
            'classification' => 'historical_evidence',
            'reason' => 'The published v1 schema identifier is immutable; a Dovra-owned successor must use a new version rather than rewriting history.',
            'removal_program' => 'Program 06',
        ],
    ],
];
