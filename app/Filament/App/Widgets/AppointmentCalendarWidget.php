<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\AppointmentResource;
use App\Models\Appointment;
use Filament\Widgets\Widget;

/**
 * AppointmentCalendarWidget - Visual Schedule Display
 *
 * Temporarily downgraded to a base Filament widget because the
 * saade/filament-fullcalendar dependency is no longer installed.
 *
 * SECURITY: Queries are automatically tenant-scoped via BelongsToTenant trait.
 */
class AppointmentCalendarWidget extends Widget
{
    protected string $view = 'filament.app.widgets.appointment-calendar-placeholder';

    /**
     * Fetch appointments for the calendar view.
     *
     * @param array $fetchInfo Contains 'start' and 'end' dates from FullCalendar
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
}
