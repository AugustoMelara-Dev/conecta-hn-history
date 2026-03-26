<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerJourneyRunRepositoryInterface;
use App\Models\CustomerJourneyRun;
use Illuminate\Support\Collection;

final class EloquentCustomerJourneyRunRepository implements CustomerJourneyRunRepositoryInterface
{
    public function firstOrNewByConversation(int $conversationId): CustomerJourneyRun
    {
        return CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'customer_conversation_id' => $conversationId,
            ]);
    }

    public function dueRuns(?string $tenantId = null, int $limit = 50): Collection
    {
        $query = CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->with([
                'conversation' => fn ($builder) => $builder->withoutGlobalScopes(),
                'customerProfile' => fn ($builder) => $builder->withoutGlobalScopes()->with('tenant'),
            ])
            ->where('status', 'active')
            ->whereIn('sequence_state', ['waiting_customer', 'stalled'])
            ->whereNotNull('step_due_at')
            ->where('step_due_at', '<=', now())
            ->orderBy('step_due_at')
            ->limit(max(1, $limit));

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        /** @var Collection<int, CustomerJourneyRun> $runs */
        $runs = $query->get();

        return $runs;
    }

    public function save(CustomerJourneyRun $run): CustomerJourneyRun
    {
        $run->save();

        /** @var CustomerJourneyRun $fresh */
        $fresh = $run->fresh();

        return $fresh;
    }
}
