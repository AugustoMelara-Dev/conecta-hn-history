<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class AppointmentCalendarWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.appointment-calendar-placeholder';

    /**
     * @param  array{start: string, end: string}  $fetchInfo
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(array $fetchInfo): array
    {
        /** @var Collection<int, Appointment> $appointments */
        $appointments = Appointment::query()
            ->with(['service', 'provider'])
            ->where('scheduled_at', '>=', $fetchInfo['start'])
            ->where('scheduled_at', '<=', $fetchInfo['end'])
            ->get();

        return $appointments
            ->map(function (Appointment $appointment): array {
                $service = $appointment->service;
                $provider = $appointment->provider;

                return [
                    'id' => $appointment->getKey(),
                    'title' => sprintf(
                        '%s - %s',
                        $appointment->customer_display_name,
                        $service instanceof Service ? $service->name : ''
                    ),
                    'start' => $appointment->scheduled_at,
                    'end' => $appointment->end_at,
                    'backgroundColor' => $this->getStatusColor($appointment->status),
                    'borderColor' => $this->getStatusColor($appointment->status),
                    'url' => AppointmentResource::getUrl('edit', ['record' => $appointment]),
                    'extendedProps' => [
                        'status' => $appointment->status,
                        'provider' => $provider instanceof ServiceProvider ? $provider->name : null,
                    ],
                ];
            })
            ->all();
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_PENDING => '#f59e0b',
            Appointment::STATUS_CONFIRMED => '#3b82f6',
            Appointment::STATUS_COMPLETED => '#22c55e',
            Appointment::STATUS_CANCELLED => '#ef4444',
            default => '#6b7280',
        };
    }

    /**
     * @return array<string, mixed>
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
