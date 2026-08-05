<x-filament-widgets::widget>
    <x-filament::section data-testid="operational-cockpit-widget">
        <div data-operational-cockpit>
            <script type="application/json" data-operational-cockpit-payload>
                @json(['snapshot' => $snapshot, 'status' => $status])
            </script>
            <div data-operational-cockpit-root aria-live="polite">
                <p class="biz-muted">Loading operational cockpit</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
