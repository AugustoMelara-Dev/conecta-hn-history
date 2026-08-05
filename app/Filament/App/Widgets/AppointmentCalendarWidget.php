<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\AppointmentResource;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * AppointmentCalendarWidget - Visual Schedule Display
 *
 * Displays tenant appointments in a FullCalendar interface.
 * Color-coded by status for quick visual identification.
 *
 * SECURITY: Queries are automatically tenant-scoped via BelongsToTenant trait.
 */
class AppointmentCalendarWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.appointment-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    public int $weekOffset = 0;

    public function previousWeek(): void
    {
        $this->weekOffset--;
    }

    public function nextWeek(): void
    {
        $this->weekOffset++;
    }

    public function currentWeek(): void
    {
        $this->weekOffset = 0;
    }

    /**
     * Fetch appointments for the calendar view.
     *
     * @param  array  $fetchInfo  Contains 'start' and 'end' dates from FullCalendar
     * @return array Events formatted for FullCalendar
     */
    public function fetchEvents(array $fetchInfo): array
    {
        return Appointment::query()
            ->with(['service', 'provider'])
            ->where('scheduled_at', '>=', $fetchInfo['start'])
            ->where('scheduled_at', '<=', $fetchInfo['end'])
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'title' => "{$appointment->customer_display_name} - {$appointment->service?->name}",
                'start' => $appointment->scheduled_at,
                'end' => $appointment->end_at,
                'backgroundColor' => $this->getStatusColor($appointment->status),
                'borderColor' => $this->getStatusColor($appointment->status),
                'url' => AppointmentResource::getUrl('edit', ['record' => $appointment]),
                'extendedProps' => [
                    'status' => $appointment->status,
                    'provider' => $appointment->provider?->name,
                ],
            ])
            ->all();
    }

    /**
     * Map appointment status to calendar color.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_PENDING => '#f59e0b',   // amber-500
            Appointment::STATUS_CONFIRMED => '#3b82f6', // blue-500
            Appointment::STATUS_COMPLETED => '#22c55e', // green-500
            Appointment::STATUS_CANCELLED => '#ef4444', // red-500
            default => '#6b7280',                       // gray-500
        };
    }

    /**
     * Calendar configuration options.
     */
    public function config(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
            'initialView' => 'timeGridWeek',
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '22:00:00',
            'nowIndicator' => true,
            'selectable' => false,
            'eventClick' => 'function(info) { if(info.event.url) { window.location = info.event.url; info.jsEvent.preventDefault(); } }',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $start = CarbonImmutable::now(config('app.timezone'))
            ->startOfWeek()
            ->addWeeks($this->weekOffset);
        $end = $start->endOfWeek();
        $events = collect($this->fetchEvents([
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ]));

        return [
            'weekLabel' => sprintf(
                '%s — %s',
                $start->translatedFormat('j M'),
                $end->translatedFormat('j M Y'),
            ),
            'days' => collect(range(0, 6))
                ->map(function (int $offset) use ($events, $start): array {
                    $date = $start->addDays($offset);

                    return [
                        'date' => $date->toDateString(),
                        'label' => $date->translatedFormat('D j M'),
                        'events' => $events
                            ->filter(fn (array $event): bool => CarbonImmutable::parse($event['start'])
                                ->isSameDay($date))
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
        ];
    }
}
