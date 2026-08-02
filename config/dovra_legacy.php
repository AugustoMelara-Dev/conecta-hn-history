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
            'path' => 'app/Application/Ai/PrivateGroundedAgent.php',
            'classification' => 'operational_compatibility',
            'reason' => 'The private-result encryption fallback remains stable until retained ciphertext is covered by a key-version migration.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'app/Application/Privacy/AccountPrivacyService.php',
            'classification' => 'historical_evidence',
            'reason' => 'The v1 account export format identifier remains readable while Program 11 introduces a versioned Dovra successor.',
            'removal_program' => 'Program 11',
        ],
        [
            'path' => 'app/Application/Privacy/Dovra/PrivacyRequestService.php',
            'classification' => 'operational_compatibility',
            'reason' => 'The Dovra privacy adapter preserves the historical export format identifier while retained consumers migrate to the v2 envelope.',
            'removal_program' => 'Program 11',
        ],
        [
            'path' => 'app/Console/Commands/ObservabilitySmokeCommand.php',
            'classification' => 'operational_compatibility',
            'reason' => 'Existing alert routing may match the smoke identifier until Program 12 migrates dashboards and runbooks.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'app/Console/Commands/StorageSmokeCommand.php',
            'classification' => 'operational_compatibility',
            'reason' => 'Existing storage-smoke cleanup and runbooks retain this object marker until Program 12 migration evidence exists.',
            'removal_program' => 'Program 12',
        ],
        [
            'path' => 'app/DataTransferObjects/Payment/PaymentData.php',
            'classification' => 'legal_dependency',
            'reason' => 'The payment statement descriptor remains tied to the current provider and legal account until Program 09 reconfigures it explicitly.',
            'removal_program' => 'Program 09',
        ],
        [
            'path' => 'app/Services/TenantRegistrationService.php',
            'classification' => 'operational_compatibility',
            'reason' => 'Legacy brand slugs remain reserved to prevent impersonation and broken historical links during the tenancy migration.',
            'removal_program' => 'Program 03',
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
