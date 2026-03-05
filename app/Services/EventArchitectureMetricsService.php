<?php

declare(strict_types=1);

namespace App\Services;

use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * EventArchitectureMetricsService
 *
 * Provides real-time compliance metrics for the /admin/metrics/architecture dashboard.
 */
class EventArchitectureMetricsService
{
    public function getMetrics(): array
    {
        $allEvents = $this->findAllEvents();
        $metrics = [
            'total_events' => count($allEvents),
            'events' => [],
            'violations' => [],
        ];

        $compliantCount = 0;

        foreach ($allEvents as $eventClass) {
            // Skip Broadcast events
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $reflection = new ReflectionClass($eventClass);
            $compliance = $this->checkCompliance($eventClass, $reflection);

            $metrics['events'][$eventClass] = $compliance;

            if ($compliance['is_compliant']) {
                $compliantCount++;
            } else {
                foreach ($compliance['violations'] as $rule => $message) {
                    $metrics['violations'][] = [
                        'event' => $reflection->getShortName(),
                        'rule' => $rule,
                        'message' => $message,
                        'severity' => $this->getSeverity($rule),
                    ];
                }
            }
        }

        $total = count($allEvents) - $this->countBroadcastEvents($allEvents);

        $metrics['summary'] = [
            'total_events' => $total,
            'compliant_count' => $compliantCount,
            'non_compliant_count' => $total - $compliantCount,
            'compliance_percentage' => $total > 0 ? round(($compliantCount / $total) * 100, 1) : 0,
            'health_status' => $this->getHealthStatus($compliantCount, $total),
            'last_checked_at' => now()->format('Y-m-d H:i:s'),
        ];

        return $metrics;
    }

    private function checkCompliance(string $eventClass, ReflectionClass $reflection): array
    {
        $violations = [];

        // Rule 1: final
        if (! $reflection->isFinal()) {
            $violations['final'] = 'Missing final keyword';
        }

        // Rule 2: readonly
        if (method_exists($reflection, 'isReadOnly') && ! $reflection->isReadOnly()) {
            $violations['readonly'] = 'Missing readonly keyword';
        }

        // Rule 3: Interface
        $interfaces = $reflection->getInterfaceNames();
        $hasDomain = in_array('App\Domain\Shared\Contracts\Events\DomainEvent', $interfaces, true);
        $hasAudit = in_array('App\Contracts\Events\AuditEvent', $interfaces, true);

        if (! $hasDomain && ! $hasAudit) {
            $violations['interface'] = 'Must implement DomainEvent or AuditEvent';
        }

        // Rule 4: Serialization
        $hasToPrimitives = $reflection->hasMethod('toPrimitives');
        $hasToAuditLog = $reflection->hasMethod('toAuditLog');

        if (! $hasToPrimitives && ! $hasToAuditLog) {
            $violations['serialization'] = 'Missing toPrimitives() or toAuditLog()';
        }

        // Rule 5: HasDomainMetadata trait
        $traits = $reflection->getTraitNames();
        if (! in_array('App\Domain\Shared\Traits\HasDomainMetadata', $traits, true)) {
            $violations['metadata'] = 'Missing HasDomainMetadata trait';
        }

        // Rule 6: No SerializesModels
        if (in_array('Illuminate\\Queue\\SerializesModels', $traits, true)) {
            $violations['serializes_models'] = 'Uses forbidden SerializesModels trait';
        }

        return [
            'is_compliant' => empty($violations),
            'violations' => $violations,
            'violation_count' => count($violations),
        ];
    }

    private function findAllEvents(): array
    {
        $finder = new Finder;
        $finder->in(app_path('Events'))->name('*.php')->files();

        $events = [];
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fullClass = 'App\\Events\\'.$className;

            if (class_exists($fullClass) && ! interface_exists($fullClass)) {
                $events[] = $fullClass;
            }
        }

        return $events;
    }

    private function countBroadcastEvents(array $events): int
    {
        return count(array_filter($events, fn ($e) => str_contains($e, '\\Broadcast\\')));
    }

    private function getHealthStatus(int $compliant, int $total): string
    {
        if ($total === 0) {
            return 'unknown';
        }
        $pct = ($compliant / $total) * 100;
        if ($pct === 100.0) {
            return 'healthy';
        }
        if ($pct >= 80) {
            return 'warning';
        }

        return 'critical';
    }

    private function getSeverity(string $rule): string
    {
        $critical = ['interface', 'serialization', 'serializes_models'];

        return in_array($rule, $critical) ? 'critical' : 'warning';
    }
}
