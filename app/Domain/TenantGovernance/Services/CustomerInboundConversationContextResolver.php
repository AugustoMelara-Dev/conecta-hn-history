<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class CustomerInboundConversationContextResolver
{
    public function __construct(
        private readonly CustomerConversationRouting $routing,
        private readonly CustomerNoteRepositoryInterface $customerNotes,
    ) {}

    /**
     * @return array{
     *     profile:?CustomerProfile,
     *     existingNote:?CustomerNote,
     *     routing:array{assignment_group:string,assignment_group_label:string,response_sla_minutes:int,escalation_minutes:int},
     *     message:string,
     *     priority:string,
     *     conversationKey:string,
     *     assignedUserId:?string,
     *     locationId:?string,
     *     responseDueAt:Carbon,
     *     escalationDueAt:Carbon
     * }
     */
    public function resolve(CustomerTouchpoint $touchpoint, string $body, Carbon $occurredAt, string $channel): array
    {
        $profile = $touchpoint->customerProfile()->withoutGlobalScopes()->with(['assignedUser', 'location', 'tenant'])->first();
        $existingNote = $this->latestConversation($touchpoint, $channel);
        $routing = $profile instanceof CustomerProfile
            ? $this->routing->resolve($profile, $channel)
            : $this->fallbackRouting($channel);
        $message = trim($body) !== '' ? trim($body) : 'Respuesta recibida del cliente.';
        $conversationKey = (string) ($existingNote?->conversation_key ?: data_get($touchpoint->metadata, 'conversation_key') ?: Str::uuid()->toString());
        $assignedUserId = $existingNote instanceof CustomerNote
            ? $existingNote->assigned_user_id
            : ($profile instanceof CustomerProfile ? $profile->assigned_user_id : null);
        $locationId = $existingNote instanceof CustomerNote
            ? $existingNote->location_id
            : ($profile instanceof CustomerProfile ? $profile->location_id : null);
        $responseDueAt = $occurredAt->copy()->addMinutes((int) $routing['response_sla_minutes']);

        return [
            'profile' => $profile instanceof CustomerProfile ? $profile : null,
            'existingNote' => $existingNote,
            'routing' => $routing,
            'message' => $message,
            'priority' => $channel === 'whatsapp' ? 'high' : 'normal',
            'conversationKey' => $conversationKey,
            'assignedUserId' => $assignedUserId,
            'locationId' => $locationId,
            'responseDueAt' => $responseDueAt,
            'escalationDueAt' => $occurredAt->copy()->addMinutes((int) $routing['escalation_minutes']),
        ];
    }

    private function latestConversation(CustomerTouchpoint $touchpoint, string $channel): ?CustomerNote
    {
        $window = max(1, (int) config('customer-loyalty.conversation.window_hours', 72));

        return $this->customerNotes->latestRecentConversationNote(
            (string) $touchpoint->tenant_id,
            (int) $touchpoint->customer_profile_id,
            $channel,
            $window,
        );
    }

    /**
     * @return array{assignment_group:string,assignment_group_label:string,response_sla_minutes:int,escalation_minutes:int}
     */
    private function fallbackRouting(string $channel): array
    {
        $groups = (array) config('customer-operations.groups', []);
        $group = 'sales';
        $definition = (array) ($groups[$group] ?? []);
        $channelDelta = (int) data_get(config('customer-operations.channels', []), "{$channel}.sla_delta_minutes", 0);

        return [
            'assignment_group' => $group,
            'assignment_group_label' => (string) ($definition['label'] ?? 'Ventas'),
            'response_sla_minutes' => max(15, ((int) ($definition['sla_minutes'] ?? 90)) + $channelDelta),
            'escalation_minutes' => max(30, (int) ($definition['escalation_minutes'] ?? 120)),
        ];
    }
}
