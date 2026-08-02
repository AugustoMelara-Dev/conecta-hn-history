<?php

declare(strict_types=1);

return [
    'default_timeout_seconds' => 30,
    'contracts' => [
        // No API or MCP route is bound in this parallel slice.
        'api_route_bound' => false,
        'mcp_route_bound' => false,
    ],
    'providers' => [],
];
