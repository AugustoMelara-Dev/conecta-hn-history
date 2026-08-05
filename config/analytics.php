<?php

declare(strict_types=1);

return [
    // Raw public rows may contain pseudonymous network identifiers. Durable
    // aggregate metrics outlive them; the scheduled model pruner removes them.
    'interaction_retention_days' => (int) env('ANALYTICS_INTERACTION_RETENTION_DAYS', 90),
];
