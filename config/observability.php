<?php

declare(strict_types=1);

return [
    'opentelemetry' => [
        'enabled' => (bool) env('OTEL_PHP_AUTOLOAD_ENABLED', false),
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
        'service_name' => env('OTEL_SERVICE_NAME', 'conecta-hn'),
        'traces_exporter' => env('OTEL_TRACES_EXPORTER', 'otlp'),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
    ],
];
