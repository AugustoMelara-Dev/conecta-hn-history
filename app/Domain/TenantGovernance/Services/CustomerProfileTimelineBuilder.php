<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\Order;
use Illuminate\Support\Collection;

class CustomerProfileTimelineBuilder
{
    /**
     * @return list<array{type:string,title:string,copy:string,channel:?string,when:string,color:string}>
     */
    public function build(CustomerProfile $profile, int $limit = 18): array
    {
        $profileId = (int) $profile->getKey();
        $tenantId = (string) $profile->tenant_id;

        $timeline = Collection::make()
            ->concat($this->orders($tenantId, $profileId))
            ->concat($this->appointments($tenantId, $profileId))
            ->concat($this->leads($tenantId, $profileId))
            ->concat($this->touchpoints($tenantId, $profileId))
            ->concat($this->notes($tenantId, $profileId))
            ->concat($this->tasks($tenantId, $profileId))
            ->concat($this->loyalty($tenantId, $profileId))
            ->sortByDesc('sort_at')
            ->take($limit)
            ->values()
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'title' => $item['title'],
                'copy' => $item['copy'],
                'channel' => $item['channel'],
                'when' => $item['sort_at']->diffForHumans(),
                'color' => $item['color'],
            ]);

        return $timeline->all();
    }

    private function orders(string $tenantId, int $profileId): Collection
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Order $order): array => [
                'type' => 'order',
                'title' => 'Pedido '.$order->order_number,
                'copy' => 'Pedido '.mb_strtolower((string) $order->status_label).' por L. '.number_format((float) $order->total, 2).'.',
                'channel' => $order->source,
                'sort_at' => $order->created_at ?? now(),
                'color' => 'success',
            ]);
    }

    private function appointments(string $tenantId, int $profileId): Collection
    {
        return Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'type' => 'appointment',
                'title' => 'Cita '.mb_strtolower($appointment->status_label),
                'copy' => 'Programada para '.$appointment->scheduled_at?->format('d/m/Y H:i').' con estado '.$appointment->status_label.'.',
                'channel' => 'agenda',
                'sort_at' => $appointment->scheduled_at ?? $appointment->created_at ?? now(),
                'color' => 'info',
            ]);
    }

    private function leads(string $tenantId, int $profileId): Collection
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Lead $lead): array => [
                'type' => 'lead',
                'title' => 'Lead '.mb_strtolower((string) $lead->status),
                'copy' => trim((string) ($lead->message ?: 'Ingreso por '.($lead->source ?: 'canal directo').'.')),
                'channel' => $lead->source,
                'sort_at' => $lead->created_at ?? now(),
                'color' => 'warning',
            ]);
    }

    private function touchpoints(string $tenantId, int $profileId): Collection
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (CustomerTouchpoint $touchpoint): array => [
                'type' => 'touchpoint',
                'title' => ucfirst((string) $touchpoint->channel).' '.mb_strtolower($touchpoint->delivery_status_label),
                'copy' => trim((string) ($touchpoint->subject ?: str($touchpoint->message)->limit(110))),
                'channel' => $touchpoint->channel,
                'sort_at' => $touchpoint->replied_at
                    ?? $touchpoint->opened_at
                    ?? $touchpoint->delivered_at
                    ?? $touchpoint->sent_at
                    ?? $touchpoint->created_at
                    ?? now(),
                'color' => 'primary',
            ]);
    }

    private function notes(string $tenantId, int $profileId): Collection
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (CustomerNote $note): array => [
                'type' => 'note',
                'title' => $note->note_type_label.' '.mb_strtolower($note->status_label),
                'copy' => trim((string) str($note->body)->limit(110)),
                'channel' => $note->source_channel,
                'sort_at' => $note->last_customer_message_at
                    ?? $note->handled_at
                    ?? $note->created_at
                    ?? now(),
                'color' => $note->sla_color,
            ]);
    }

    private function tasks(string $tenantId, int $profileId): Collection
    {
        return CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (CustomerTask $task): array => [
                'type' => 'task',
                'title' => $task->task_type_label.' '.mb_strtolower($task->status_label),
                'copy' => trim((string) ($task->title ?: $task->body ?: 'Seguimiento operativo del cliente.')),
                'channel' => $task->source_channel,
                'sort_at' => $task->completed_at ?? $task->due_at ?? $task->created_at ?? now(),
                'color' => $task->sla_color,
            ]);
    }

    private function loyalty(string $tenantId, int $profileId): Collection
    {
        return CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('awarded_at')
            ->limit(8)
            ->get()
            ->map(fn (CustomerLoyaltyLedger $entry): array => [
                'type' => 'loyalty',
                'title' => $entry->type_label,
                'copy' => trim((string) ($entry->description ?: 'Movimiento de '.abs((int) $entry->points_delta).' puntos.')),
                'channel' => 'loyalty',
                'sort_at' => $entry->awarded_at ?? $entry->created_at ?? now(),
                'color' => 'success',
            ]);
    }
}
