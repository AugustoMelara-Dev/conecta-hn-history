<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]" data-testid="reports-schedule">
        <section class="biz-card h-fit" aria-labelledby="reports-schedule-heading">
            <p class="biz-kicker">Programar reporte</p>
            <h2 id="reports-schedule-heading" class="biz-title">Entrega recurrente</h2>
            <p class="biz-muted">Configura una cadencia y hasta diez destinatarios verificados.</p>
            <form wire:submit="createSchedule" class="mt-6 space-y-4">
                <label class="block text-sm font-medium">Reporte<select wire:model="scheduleReportKey" required class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Reporte a programar"><option value="">Selecciona uno</option>@foreach($reports as $report)<option value="{{ $report->getKey()->value }}">{{ $report->getName() }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">Frecuencia<select wire:model="scheduleFrequency" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Frecuencia de entrega">@foreach($frequencies as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">Formato<select wire:model="scheduleFormat" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Formato de entrega">@foreach($formats as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">Destinatarios<textarea wire:model="scheduleRecipients" rows="4" required class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-describedby="recipient-help"></textarea></label>
                <p id="recipient-help" class="biz-muted text-xs">Separa correos con coma, punto y coma o una línea nueva. Máximo 10.</p>
                <x-filament::button type="submit" class="w-full" aria-label="Crear programación de reporte">Crear programación</x-filament::button>
            </form>
        </section>

        <section aria-labelledby="scheduled-reports-heading">
            <div class="mb-4"><p class="biz-kicker">Agenda activa</p><h2 id="scheduled-reports-heading" class="biz-title">Próximas entregas</h2></div>
            <div class="grid gap-4">
                @forelse($schedules as $schedule)
                    <article class="biz-card"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="biz-heading">{{ \App\Domain\Reporting\Enums\ReportKey::tryFrom($schedule->report_key)?->label() ?? $schedule->report_key }}</h3><p class="biz-muted">{{ $schedule->frequency_enum->label() }} · {{ strtoupper($schedule->format) }} · {{ count($schedule->recipients ?? []) }} destinatarios</p></div><x-filament::badge :color="$schedule->is_active ? 'success' : 'gray'">{{ $schedule->is_active ? 'Activa' : 'Pausada' }}</x-filament::badge></div><p class="mt-4 text-sm">Próxima ejecución: <strong>{{ $schedule->next_run_at?->translatedFormat('d M Y, H:i') ?? 'por calcular' }}</strong></p></article>
                @empty
                    <div class="biz-empty-state" role="status">Todavía no hay entregas programadas.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
