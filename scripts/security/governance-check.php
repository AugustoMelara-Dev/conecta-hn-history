<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [];

$checks[] = requireFile(
    $root.'/docs/security/security-operations.md',
    'Security operations runbook exists.'
);

$checks[] = requireFile(
    $root.'/config/rbac.php',
    'RBAC configuration exists.'
);

$checks[] = requireFile(
    $root.'/routes/api.php',
    'API routes file exists.'
);

$checks = array_merge($checks, checkRbacRoles($root.'/config/rbac.php'));
$checks = array_merge($checks, checkSensitivePermissionBoundaries($root.'/config/rbac.php'));
$checks = array_merge($checks, checkWebhookRoute($root.'/routes/api.php', '/webhooks/payment'));
$checks = array_merge($checks, checkWebhookRoute($root.'/routes/api.php', '/webhooks/resend/email'));
$checks = array_merge($checks, checkWebhookRoute($root.'/routes/api.php', '/webhooks/whatsapp-cloud'));
$checks = array_merge($checks, checkWebhookController(
    $root.'/app/Http/Controllers/Webhook/PaymentWebhookController.php',
    [
        'verifySignature(',
        'hash_hmac(',
        'hash_equals(',
    ],
    'Payment webhook controller enforces signature verification.'
));
$checks = array_merge($checks, checkWebhookController(
    $root.'/app/Http/Controllers/Webhook/ResendWebhookController.php',
    [
        '->webhooks',
        '->verify(',
        'webhook_secret',
        'Invalid webhook signature.',
    ],
    'Resend webhook controller validates provider signature.'
));
$checks = array_merge($checks, checkWebhookController(
    $root.'/app/Http/Controllers/Webhook/WhatsAppCloudWebhookController.php',
    [
        'hub_verify_token',
        'x-hub-signature-256',
        'hash_equals(',
    ],
    'WhatsApp webhook controller validates verification token and request signature.'
));
$checks = array_merge($checks, requireTest(
    $root.'/tests/Feature/Http/Controllers/Payment/PaymentWebhookControllerTest.php',
    'Payment webhook feature tests exist.'
));
$checks = array_merge($checks, requireTest(
    $root.'/tests/Feature/CRM/ResendWebhookControllerTest.php',
    'Resend webhook feature tests exist.'
));
$checks = array_merge($checks, requireTest(
    $root.'/tests/Feature/CRM/WhatsAppCloudWebhookControllerTest.php',
    'WhatsApp webhook feature tests exist.'
));
$checks = array_merge($checks, checkRunbookSections(
    $root.'/docs/security/security-operations.md',
    [
        '## Politica de parchado',
        '## Revision de permisos y RBAC',
        '## Auditoria de webhooks',
        '## Rotacion de llaves y secretos',
    ]
));

$hasFailures = false;

foreach ($checks as $check) {
    $status = $check['ok'] ? '[PASS]' : '[FAIL]';
    echo $status.' '.$check['message'].PHP_EOL;

    if (! $check['ok']) {
        $hasFailures = true;
    }
}

exit($hasFailures ? 1 : 0);

/**
 * @return array{ok: bool, message: string}
 */
function requireFile(string $path, string $message): array
{
    return [
        'ok' => is_file($path),
        'message' => $message,
    ];
}

/**
 * @return list<array{ok: bool, message: string}>
 */
function requireTest(string $path, string $message): array
{
    return [requireFile($path, $message)];
}

/**
 * @return list<array{ok: bool, message: string}>
 */
function checkRbacRoles(string $path): array
{
    if (! is_file($path)) {
        return [[
            'ok' => false,
            'message' => 'RBAC configuration could not be inspected.',
        ]];
    }

    $contents = (string) file_get_contents($path);
    $requiredRoles = ['Role::OWNER', 'Role::ADMIN', 'Role::MANAGER', 'Role::OPERATOR', 'Role::VIEWER'];
    $checks = [];

    foreach ($requiredRoles as $role) {
        $checks[] = [
            'ok' => str_contains($contents, $role),
            'message' => sprintf('RBAC includes %s baseline.', $role),
        ];
    }

    return $checks;
}

/**
 * @return list<array{ok: bool, message: string}>
 */
function checkSensitivePermissionBoundaries(string $path): array
{
    if (! is_file($path)) {
        return [[
            'ok' => false,
            'message' => 'Sensitive permission boundaries could not be verified.',
        ]];
    }

    $contents = (string) file_get_contents($path);
    $roleBlocks = [
        'owner' => extractRoleBlock($contents, 'OWNER'),
        'admin' => extractRoleBlock($contents, 'ADMIN'),
        'manager' => extractRoleBlock($contents, 'MANAGER'),
        'operator' => extractRoleBlock($contents, 'OPERATOR'),
        'viewer' => extractRoleBlock($contents, 'VIEWER'),
    ];

    $checks = [];
    foreach ($roleBlocks as $role => $block) {
        $checks[] = [
            'ok' => $block !== null,
            'message' => sprintf('RBAC block for role %s is parseable.', $role),
        ];
    }

    if (in_array(null, $roleBlocks, true)) {
        return $checks;
    }

    /** @var array<string, string> $roleBlocks */
    $roleBlocks = $roleBlocks;

    $checks[] = [
        'ok' => str_contains($roleBlocks['owner'], 'Permission::cases()') || blockContainsAll($roleBlocks['owner'], [
            'Permission::SETTINGS_UPDATE->value',
            'Permission::STAFF_ASSIGN_ROLES->value',
            'Permission::WEBHOOKS_VIEW->value',
            'Permission::PAYMENTS_RECORD->value',
            'Permission::ORDERS_EXPORT->value',
            'Permission::CUSTOMERS_EXPORT->value',
        ]),
        'message' => 'Owner keeps all sensitive permissions.',
    ];

    $checks[] = [
        'ok' => blockContainsAll($roleBlocks['admin'], [
            'Permission::SETTINGS_UPDATE->value',
            'Permission::STAFF_ASSIGN_ROLES->value',
            'Permission::WEBHOOKS_VIEW->value',
            'Permission::PAYMENTS_RECORD->value',
            'Permission::ORDERS_EXPORT->value',
            'Permission::CUSTOMERS_EXPORT->value',
        ]),
        'message' => 'Admin keeps all sensitive permissions.',
    ];

    $checks[] = [
        'ok' => blockContainsAll($roleBlocks['manager'], [
            'Permission::PAYMENTS_RECORD->value',
            'Permission::ORDERS_EXPORT->value',
        ]) && blockContainsNone($roleBlocks['manager'], [
            'Permission::SETTINGS_UPDATE->value',
            'Permission::STAFF_ASSIGN_ROLES->value',
            'Permission::WEBHOOKS_VIEW->value',
            'Permission::CUSTOMERS_EXPORT->value',
        ]),
        'message' => 'Manager keeps operational sensitive scope only (payments/order export).',
    ];

    $checks[] = [
        'ok' => blockContainsNone($roleBlocks['operator'], [
            'Permission::SETTINGS_UPDATE->value',
            'Permission::STAFF_ASSIGN_ROLES->value',
            'Permission::WEBHOOKS_VIEW->value',
            'Permission::PAYMENTS_RECORD->value',
            'Permission::ORDERS_EXPORT->value',
            'Permission::CUSTOMERS_EXPORT->value',
        ]),
        'message' => 'Operator is excluded from sensitive permissions.',
    ];

    $checks[] = [
        'ok' => blockContainsNone($roleBlocks['viewer'], [
            'Permission::SETTINGS_UPDATE->value',
            'Permission::STAFF_ASSIGN_ROLES->value',
            'Permission::WEBHOOKS_VIEW->value',
            'Permission::PAYMENTS_RECORD->value',
            'Permission::ORDERS_EXPORT->value',
            'Permission::CUSTOMERS_EXPORT->value',
        ]),
        'message' => 'Viewer is excluded from sensitive permissions.',
    ];

    return $checks;
}

function extractRoleBlock(string $contents, string $role): ?string
{
    $startNeedle = sprintf('Role::%s->value =>', $role);
    $start = strpos($contents, $startNeedle);

    if ($start === false) {
        return null;
    }

    $nextRole = strpos($contents, 'Role::', $start + strlen($startNeedle));
    if ($nextRole !== false) {
        return substr($contents, $start, $nextRole - $start);
    }

    $presetsStart = strpos($contents, "'presets' => [", $start);
    if ($presetsStart !== false) {
        return substr($contents, $start, $presetsStart - $start);
    }

    return substr($contents, $start);
}

/**
 * @param  list<string>  $needles
 */
function blockContainsAll(string $block, array $needles): bool
{
    foreach ($needles as $needle) {
        if (! str_contains($block, $needle)) {
            return false;
        }
    }

    return true;
}

/**
 * @param  list<string>  $needles
 */
function blockContainsNone(string $block, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($block, $needle)) {
            return false;
        }
    }

    return true;
}

/**
 * @return list<array{ok: bool, message: string}>
 */
function checkWebhookRoute(string $path, string $route): array
{
    if (! is_file($path)) {
        return [[
            'ok' => false,
            'message' => sprintf('Route %s could not be verified.', $route),
        ]];
    }

    $contents = (string) file_get_contents($path);

    return [[
        'ok' => str_contains($contents, $route),
        'message' => sprintf('Webhook route %s is registered.', $route),
    ]];
}

/**
 * @param  list<string>  $needles
 * @return list<array{ok: bool, message: string}>
 */
function checkWebhookController(string $path, array $needles, string $message): array
{
    if (! is_file($path)) {
        return [[
            'ok' => false,
            'message' => $message,
        ]];
    }

    $contents = (string) file_get_contents($path);
    $ok = true;

    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            $ok = false;
            break;
        }
    }

    return [[
        'ok' => $ok,
        'message' => $message,
    ]];
}

/**
 * @param  list<string>  $sections
 * @return list<array{ok: bool, message: string}>
 */
function checkRunbookSections(string $path, array $sections): array
{
    if (! is_file($path)) {
        return [[
            'ok' => false,
            'message' => 'Security runbook sections could not be verified.',
        ]];
    }

    $contents = (string) file_get_contents($path);
    $checks = [];

    foreach ($sections as $section) {
        $checks[] = [
            'ok' => str_contains($contents, $section),
            'message' => sprintf('Runbook includes section %s.', $section),
        ];
    }

    return $checks;
}
