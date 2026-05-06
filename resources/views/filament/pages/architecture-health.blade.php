<x-filament-panels::page>
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Events</p>
                <p class="text-3xl font-bold">{{ $this->summary['total_events'] ?? 0 }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Compliant</p>
                <p class="text-3xl font-bold text-success-600">{{ $this->summary['compliant_count'] ?? 0 }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Violations</p>
                <p class="text-3xl font-bold {{ ($this->summary['non_compliant_count'] ?? 0) > 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ $this->summary['non_compliant_count'] ?? 0 }}
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Compliance</p>
                <p class="text-3xl font-bold">{{ $this->summary['compliance_percentage'] ?? 0 }}%</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Health Status Banner --}}
    @if (($this->summary['health_status'] ?? '') === 'healthy')
        <x-filament::section class="bg-success-50 dark:bg-success-900/20 mb-6">
            <h3 class="text-lg font-semibold text-success-700 dark:text-success-400">
                ✅ All Systems Healthy - 100% Compliance
            </h3>
            <p class="text-sm text-success-600 dark:text-success-500">
                All {{ $this->summary['total_events'] }} events comply with ADR-002.
            </p>
        </x-filament::section>
    @elseif (($this->summary['health_status'] ?? '') === 'warning')
        <x-filament::section class="bg-warning-50 dark:bg-warning-900/20 mb-6">
            <h3 class="text-lg font-semibold text-warning-700 dark:text-warning-400">
                ⚠️ Warning - Below 100%
            </h3>
            <p class="text-sm text-warning-600 dark:text-warning-500">
                {{ $this->summary['non_compliant_count'] }} event(s) have violations.
            </p>
        </x-filament::section>
    @else
        <x-filament::section class="bg-danger-50 dark:bg-danger-900/20 mb-6">
            <h3 class="text-lg font-semibold text-danger-700 dark:text-danger-400">
                🚨 Critical Violations
            </h3>
            <p class="text-sm text-danger-600 dark:text-danger-500">
                {{ $this->summary['non_compliant_count'] ?? 0 }} event(s) require immediate attention.
            </p>
        </x-filament::section>
    @endif

    {{-- Violations List --}}
    @if (!empty($this->violations))
        <x-filament::section class="mb-6">
            <x-slot name="heading">📋 Violations</x-slot>
            <div class="space-y-2">
                @foreach ($this->violations as $v)
                    <div class="flex items-center gap-3 p-3 rounded-lg border-l-4 {{ $v['severity'] === 'critical' ? 'border-danger-500 bg-danger-50 dark:bg-danger-900/20' : 'border-warning-500 bg-warning-50 dark:bg-warning-900/20' }}">
                        <span>{{ $v['severity'] === 'critical' ? '❌' : '⚠️' }}</span>
                        <div>
                            <p class="font-semibold">{{ $v['event'] }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $v['rule'] }}: {{ $v['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Events Table --}}
    <x-filament::section>
        <x-slot name="heading">📊 Event Details</x-slot>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left p-2">Event</th>
                    <th class="text-left p-2">Status</th>
                    <th class="text-left p-2">Issues</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->events as $class => $compliance)
                    <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-2 font-mono text-xs">{{ class_basename($class) }}</td>
                        <td class="p-2">
                            @if ($compliance['is_compliant'])
                                <x-filament::badge color="success">✅ Compliant</x-filament::badge>
                            @else
                                <x-filament::badge color="danger">❌ {{ $compliance['violation_count'] }} issues</x-filament::badge>
                            @endif
                        </td>
                        <td class="p-2 text-gray-500">
                            {{ implode(', ', array_keys($compliance['violations'])) ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <p class="mt-4 text-xs text-gray-400">
        Last updated: {{ $this->summary['last_checked_at'] ?? now()->format('Y-m-d H:i:s') }}
    </p>
</x-filament-panels::page>
