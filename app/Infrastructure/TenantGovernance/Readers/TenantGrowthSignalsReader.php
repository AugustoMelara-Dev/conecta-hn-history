<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Readers;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Follow;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use App\Models\Waitlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class TenantGrowthSignalsReader
{
    public function __construct(
        private readonly CustomerProfileManager $customerProfileManager,
    ) {}

    /**
     * @return array<string, bool|int|float|string>
     */
    public function summarize(Tenant $tenant, string $businessType): array
    {
        $tenantId = (string) $tenant->getKey();
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $oneHundredEightyDaysAgo = now()->subDays(180)->startOfDay();

        $state = $this->defaultState();
        $state['hasWhatsApp'] = filled($tenant->whatsapp_number);

        $this->ensureCustomerProfilesBootstrapped($tenantId);

        try {
            $leadBase = Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $state['newLeadsCount'] = (clone $leadBase)->where('status', 'new')->count();
            $state['contactedLeadsCount'] = (clone $leadBase)->where('status', 'contacted')->count();
            $state['closedLeadsCount'] = (clone $leadBase)->where('status', 'closed')->count();
            $state['lostLeadsCount'] = (clone $leadBase)->where('status', 'lost')->count();

            $topLeadSource = (clone $leadBase)
                ->selectRaw('coalesce(source, "manual") as source_key, count(*) as total')
                ->groupBy('source_key')
                ->orderByDesc('total')
                ->first();

            if ($topLeadSource !== null) {
                $state['topLeadSource'] = $this->formatSourceLabel((string) data_get($topLeadSource, 'source_key', 'manual'));
            }
        } catch (\Throwable) {
            // Leads can be optional for fresh tenants.
        }

        try {
            $orderBase = Order::query()
                ->withoutGlobalScopes()
                ->without(['items'])
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->whereNotIn('status', [Order::STATUS_CANCELLED]);

            $state['orders30d'] = (clone $orderBase)->count();
            $state['grossSalesOrders30d'] = (clone $orderBase)
                ->whereIn('status', [
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_READY,
                    Order::STATUS_DELIVERED,
                ])
                ->count();
            $state['grossSales30d'] = (float) ((clone $orderBase)->sum('total') ?: 0);
            $state['averageTicket30d'] = (int) $state['orders30d'] > 0
                ? round(((float) $state['grossSales30d']) / max(1, (int) $state['orders30d']), 2)
                : 0.0;

            $topSalesChannel = (clone $orderBase)
                ->selectRaw('coalesce(source, "direct") as source_key, count(*) as total')
                ->groupBy('source_key')
                ->orderByDesc('total')
                ->first();

            if ($topSalesChannel !== null) {
                $state['topSalesChannel'] = $this->formatOrderSourceLabel((string) data_get($topSalesChannel, 'source_key', 'direct'));
            }

            $customerSnapshot = $this->customerSnapshotFromOrders($tenantId, $oneHundredEightyDaysAgo);
            $state['distinctCustomersCount'] = $customerSnapshot['distinct'];
            $state['repeatCustomerCount'] = $customerSnapshot['repeat'];
            $state['reactivationPoolCount'] = $customerSnapshot['reactivation'];

            foreach ($this->loadTopProductOffer($tenantId, $thirtyDaysAgo) as $key => $value) {
                $state[$key] = $value;
            }
        } catch (\Throwable) {
            // Orders are optional on service-first tenants.
        }

        try {
            $appointmentBase = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->whereNotIn('status', [Appointment::STATUS_CANCELLED]);

            $state['appointments30d'] = (clone $appointmentBase)->count();

            if ($businessType === BusinessType::SERVICE->value && (int) $state['distinctCustomersCount'] === 0) {
                $customerSnapshot = $this->customerSnapshotFromAppointments($tenantId, $oneHundredEightyDaysAgo);
                $state['distinctCustomersCount'] = $customerSnapshot['distinct'];
                $state['repeatCustomerCount'] = $customerSnapshot['repeat'];
                $state['reactivationPoolCount'] = $customerSnapshot['reactivation'];
            }

            if (in_array($businessType, [BusinessType::SERVICE->value, BusinessType::HYBRID->value], true)) {
                foreach ($this->loadTopServiceOffer($tenantId, $thirtyDaysAgo) as $key => $value) {
                    $state[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // Appointments are optional on catalog-first tenants.
        }

        try {
            $analyticsBase = TenantAnalytic::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('date', '>=', $thirtyDaysAgo->toDateString());

            $state['visits30d'] = (int) ((clone $analyticsBase)->sum(TenantAnalytic::uniqueVisitsColumn()) ?: 0);
            $state['totalVisits30d'] = (int) ((clone $analyticsBase)->sum(TenantAnalytic::totalVisitsColumn()) ?: 0);
        } catch (\Throwable) {
            // Analytics sync may still be disabled.
        }

        try {
            $profilesBase = CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $state['crmProfilesCount'] = (clone $profilesBase)->count();
            $emailOptInCount = (clone $profilesBase)->where('email_opt_in', true)->count();
            $state['averageInitialLtv'] = round((float) ((clone $profilesBase)->avg('initial_ltv') ?: 0), 2);
            $state['reactivationPoolCount'] = max(
                (int) $state['reactivationPoolCount'],
                (clone $profilesBase)->where('reactivation_score', '>=', 65)->count(),
            );
            $state['repeatCustomerCount'] = max(
                (int) $state['repeatCustomerCount'],
                (clone $profilesBase)->where('repeat_purchase_count', '>', 0)->count(),
            );
            $state['distinctCustomersCount'] = max((int) $state['distinctCustomersCount'], (int) $state['crmProfilesCount']);
            $state['reactivatedCustomers30d'] = (clone $profilesBase)
                ->whereNotNull('last_reactivated_at')
                ->where('last_reactivated_at', '>=', $thirtyDaysAgo)
                ->count();

            $topReactivationSegment = (clone $profilesBase)
                ->whereNotNull('last_reactivated_at')
                ->where('last_reactivated_at', '>=', $thirtyDaysAgo)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, count(*) as total')
                ->groupBy('segment_key')
                ->orderByDesc('total')
                ->first();

            if ($topReactivationSegment !== null) {
                $state['topReactivationSegment'] = ucfirst(str_replace('_', ' ', (string) data_get($topReactivationSegment, 'segment_key', 'new')));
            }

            $state['loyaltyReadyCustomersCount'] = (clone $profilesBase)
                ->whereIn('loyalty_tier', ['silver', 'gold', 'vip'])
                ->count();
            $state['redeemableCustomersCount'] = (clone $profilesBase)
                ->where('redeemable_rewards_count', '>', 0)
                ->count();
            $state['campaignReadyCustomersCount'] = (clone $profilesBase)
                ->where('marketing_opt_in', true)
                ->where(function ($query): void {
                    $query->where(function ($email): void {
                        $email->whereNotNull('email')->where('email_opt_in', true);
                    })->orWhere(function ($whatsapp): void {
                        $whatsapp->whereNotNull('phone')->where('whatsapp_opt_in', true);
                    });
                })
                ->count();
            $state['assignedCustomersCount'] = (clone $profilesBase)->whereNotNull('assigned_user_id')->count();
            $state['locatedCustomersCount'] = (clone $profilesBase)->whereNotNull('location_id')->count();
            $state['vipCustomersCount'] = (clone $profilesBase)
                ->where(function ($query): void {
                    $query->where('manual_segment', 'vip')
                        ->orWhere(function ($nested): void {
                            $nested->whereNull('manual_segment')->where('customer_segment', 'vip');
                        });
                })
                ->count();
            $state['dormantCustomersCount'] = (clone $profilesBase)->where('lifecycle_stage', 'dormant')->count();
            $state['optInCoverageRate'] = (int) $state['crmProfilesCount'] > 0
                ? round(($emailOptInCount / max(1, (int) $state['crmProfilesCount'])) * 100, 1)
                : 0.0;

            $topAcquisition = (clone $profilesBase)
                ->selectRaw('coalesce(acquisition_channel, "manual") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topAcquisition !== null) {
                $state['topAcquisitionChannel'] = $this->formatSourceLabel((string) data_get($topAcquisition, 'channel_key', 'manual'));
            }

            $topSegment = (clone $profilesBase)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, count(*) as total')
                ->groupBy('segment_key')
                ->orderByDesc('total')
                ->first();

            if ($topSegment !== null) {
                $state['topCustomerSegment'] = ucfirst(str_replace('_', ' ', (string) data_get($topSegment, 'segment_key', 'new')));
            }

            $repeatProfileIds = (clone $profilesBase)
                ->where('repeat_purchase_count', '>', 0)
                ->pluck('id');

            if ($repeatProfileIds->isNotEmpty()) {
                $state['repeatRevenue30d'] = round((float) Order::query()
                    ->withoutGlobalScopes()
                    ->without(['items'])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('customer_profile_id', $repeatProfileIds->all())
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->whereNotIn('status', [Order::STATUS_CANCELLED])
                    ->sum('total'), 2);
            }

            $currentCohortStart = now()->startOfMonth();
            $previousCohortStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
            $previousCohortEnd = $currentCohortStart->copy()->subSecond();

            $currentCohortBase = (clone $profilesBase)
                ->whereNotNull('first_seen_at')
                ->where('first_seen_at', '>=', $currentCohortStart);
            $previousCohortBase = (clone $profilesBase)
                ->whereNotNull('first_seen_at')
                ->whereBetween('first_seen_at', [$previousCohortStart, $previousCohortEnd]);

            $state['currentCohortCustomersCount'] = (clone $currentCohortBase)->count();
            $state['previousCohortCustomersCount'] = (clone $previousCohortBase)->count();
            $state['cohortRepeatRate'] = (int) $state['currentCohortCustomersCount'] > 0
                ? round(((clone $currentCohortBase)->where('repeat_purchase_count', '>', 0)->count() / max(1, (int) $state['currentCohortCustomersCount'])) * 100, 1)
                : 0.0;
            $state['cohortLtv'] = round((float) ((clone $currentCohortBase)->avg('total_spent') ?: 0), 2);

            $topLtvSegment = (clone $profilesBase)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, avg(total_spent) as avg_ltv')
                ->where('total_spent', '>', 0)
                ->groupBy('segment_key')
                ->orderByDesc('avg_ltv')
                ->first();

            if ($topLtvSegment !== null) {
                $state['topLtvSegment'] = ucfirst(str_replace('_', ' ', (string) data_get($topLtvSegment, 'segment_key', 'new')));
            }

            $topLtvChannel = (clone $profilesBase)
                ->selectRaw('coalesce(first_conversion_channel, acquisition_channel, "manual") as channel_key, avg(total_spent) as avg_ltv')
                ->where('total_spent', '>', 0)
                ->groupBy('channel_key')
                ->orderByDesc('avg_ltv')
                ->first();

            if ($topLtvChannel !== null) {
                $state['topLtvChannel'] = $this->formatSourceLabel((string) data_get($topLtvChannel, 'channel_key', 'manual'));
            }
        } catch (\Throwable) {
            // CRM can be empty on brand new tenants.
        }

        try {
            $state['followersCount'] = Follow::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->count();
            $state['waitlistCount'] = Waitlist::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
        } catch (\Throwable) {
            // Audience modules are optional.
        }

        try {
            $state['campaignsSent30d'] = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', $thirtyDaysAgo)
                ->count();
            $state['scheduledCampaignsCount'] = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('scheduled_at')
                ->whereIn('status', ['draft', 'scheduled', 'queued', 'sending'])
                ->count();
            $state['recurringCampaignsCount'] = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_recurring', true)
                ->count();
            $state['rewardCampaignsCount'] = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('loyalty_reward_id')
                ->count();
        } catch (\Throwable) {
            // Campaigns are optional for free tenants.
        }

        try {
            $touchpointsBase = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo);

            $state['touchpoints30d'] = (clone $touchpointsBase)
                ->where('status', CustomerTouchpoint::STATUS_SENT)
                ->count();
            $state['scheduledTouchpointRetriesCount'] = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('next_retry_at')
                ->count();
            $state['dueTouchpointRetriesCount'] = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('next_retry_at')
                ->where('next_retry_at', '<=', now())
                ->count();

            $topTemplate = (clone $touchpointsBase)
                ->selectRaw("coalesce(template_key, automation_key, 'manual') as template_key, count(*) as total, sum(case when delivery_status = 'replied' then 1 else 0 end) as replied_total")
                ->groupBy('template_key')
                ->orderByDesc('replied_total')
                ->orderByDesc('total')
                ->first();

            if ($topTemplate !== null) {
                $state['topTemplateLabel'] = $this->formatSourceLabel((string) $topTemplate->template_key);
            }

            $emailTouchpoints = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'email')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', CustomerTouchpoint::STATUS_SENT);

            $totalEmailTouchpoints = (clone $emailTouchpoints)->count();
            $state['touchpointsDelivered30d'] = (clone $emailTouchpoints)->whereNotNull('delivered_at')->count();
            $state['touchpointsOpened30d'] = (clone $emailTouchpoints)->whereNotNull('opened_at')->count();
            $state['touchpointsBounced30d'] = (clone $emailTouchpoints)
                ->where(function ($query): void {
                    $query->whereNotNull('bounced_at')->orWhereNotNull('complained_at');
                })
                ->count();

            $whatsAppTouchpoints = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'whatsapp')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', CustomerTouchpoint::STATUS_SENT);

            $state['whatsAppTouchpoints30d'] = (clone $whatsAppTouchpoints)->count();
            $state['whatsAppReplies30d'] = (clone $whatsAppTouchpoints)->whereNotNull('replied_at')->count();
            $state['deliveryRate30d'] = $totalEmailTouchpoints > 0
                ? round(((int) $state['touchpointsDelivered30d'] / $totalEmailTouchpoints) * 100, 1)
                : 0.0;
            $state['openRate30d'] = $totalEmailTouchpoints > 0
                ? round(((int) $state['touchpointsOpened30d'] / $totalEmailTouchpoints) * 100, 1)
                : 0.0;
        } catch (\Throwable) {
            // Touchpoints are optional until CRM automation starts.
        }

        try {
            $conversationBase = CustomerConversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $customerConversations30d = (clone $conversationBase)
                ->whereNotNull('last_customer_message_at')
                ->where('last_customer_message_at', '>=', $thirtyDaysAgo);
            $respondedConversations30d = (clone $customerConversations30d)
                ->whereNotNull('first_response_at');

            $conversationsCount30d = (clone $customerConversations30d)->count();
            $state['responseRate30d'] = $conversationsCount30d > 0
                ? round(((clone $respondedConversations30d)->count() / $conversationsCount30d) * 100, 1)
                : 0.0;
            $state['openRepliesCount'] = (clone $conversationBase)->whereIn('status', ['open', 'in_progress'])->count();
            $state['breachedRepliesCount'] = (clone $conversationBase)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNotNull('response_due_at')
                ->where('response_due_at', '<', now())
                ->count();
            $state['resolvedReplies30d'] = (clone $conversationBase)
                ->where('status', 'closed')
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $thirtyDaysAgo)
                ->count();

            $topClosingChannel = (clone $conversationBase)
                ->where('status', 'closed')
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $thirtyDaysAgo)
                ->selectRaw('coalesce(source_channel, "crm") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topClosingChannel !== null) {
                $state['topClosingChannel'] = $this->formatSourceLabel((string) data_get($topClosingChannel, 'channel_key', 'crm'));
            }

            $state['notes7dCount'] = CustomerNote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            $state['openCustomerTasksCount'] = CustomerTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            $state['overdueCustomerTasksCount'] = CustomerTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count();
            $state['journeysReadyCount'] = CustomerJourneyRun::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('sequence_state', ['active', 'waiting_customer'])
                ->count();
            $state['stalledJourneysCount'] = CustomerJourneyRun::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sequence_state', 'stalled')
                ->count();
            $state['averageFirstResponseMinutes'] = round((float) $respondedConversations30d
                ->get(['first_response_at', 'last_customer_message_at'])
                ->map(function (CustomerConversation $conversation): int {
                    return max(0, (int) $conversation->last_customer_message_at?->diffInMinutes($conversation->first_response_at));
                })
                ->avg(), 1);
        } catch (\Throwable) {
            // Conversations are optional until CRM starts moving.
        }

        try {
            $state['hasLoyaltyProgram'] = LoyaltyProgram::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->exists();
            $state['loyaltyRedeems30d'] = CustomerLoyaltyLedger::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', CustomerLoyaltyLedger::TYPE_REDEEM)
                ->where('awarded_at', '>=', $thirtyDaysAgo)
                ->count();

            $topLoyaltyChannel = CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('lifetime_points_earned', '>', 0)
                ->selectRaw('coalesce(last_engaged_channel, preferred_channel, acquisition_channel, "manual") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topLoyaltyChannel !== null) {
                $state['topLoyaltyChannel'] = $this->formatSourceLabel((string) data_get($topLoyaltyChannel, 'channel_key', 'manual'));
            }
        } catch (\Throwable) {
            $state['hasLoyaltyProgram'] = false;
        }

        try {
            $taskBase = OperationalPlaybookTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [
                    OperationalPlaybookTask::STATUS_OPEN,
                    OperationalPlaybookTask::STATUS_IN_PROGRESS,
                ]);

            $state['openPlaybooksCount'] = (clone $taskBase)->count();
            $state['overduePlaybooksCount'] = (clone $taskBase)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count();
        } catch (\Throwable) {
            // No active operational queue yet.
        }

        $state['recurrenceRate'] = (int) $state['distinctCustomersCount'] > 0
            ? round(((int) $state['repeatCustomerCount'] / max(1, (int) $state['distinctCustomersCount'])) * 100, 1)
            : 0.0;

        return $state;
    }

    /**
     * @return array<string, bool|int|float|string>
     */
    private function defaultState(): array
    {
        return [
            'hasWhatsApp' => false,
            'hasLoyaltyProgram' => false,
            'visits30d' => 0,
            'totalVisits30d' => 0,
            'orders30d' => 0,
            'appointments30d' => 0,
            'grossSalesOrders30d' => 0,
            'grossSales30d' => 0.0,
            'averageTicket30d' => 0.0,
            'distinctCustomersCount' => 0,
            'repeatCustomerCount' => 0,
            'reactivationPoolCount' => 0,
            'newLeadsCount' => 0,
            'contactedLeadsCount' => 0,
            'closedLeadsCount' => 0,
            'lostLeadsCount' => 0,
            'followersCount' => 0,
            'waitlistCount' => 0,
            'crmProfilesCount' => 0,
            'touchpoints30d' => 0,
            'touchpointsDelivered30d' => 0,
            'touchpointsOpened30d' => 0,
            'touchpointsBounced30d' => 0,
            'dueTouchpointRetriesCount' => 0,
            'scheduledTouchpointRetriesCount' => 0,
            'whatsAppTouchpoints30d' => 0,
            'whatsAppReplies30d' => 0,
            'campaignsSent30d' => 0,
            'openPlaybooksCount' => 0,
            'overduePlaybooksCount' => 0,
            'topLeadSource' => 'Sin datos',
            'topSalesChannel' => 'Sin datos',
            'topOfferLabel' => 'Sin datos',
            'topOfferCopy' => 'Todavia no hay suficiente actividad reciente para destacar una oferta.',
            'vipCustomersCount' => 0,
            'dormantCustomersCount' => 0,
            'notes7dCount' => 0,
            'openRepliesCount' => 0,
            'reactivatedCustomers30d' => 0,
            'loyaltyReadyCustomersCount' => 0,
            'redeemableCustomersCount' => 0,
            'breachedRepliesCount' => 0,
            'resolvedReplies30d' => 0,
            'recurrenceRate' => 0.0,
            'deliveryRate30d' => 0.0,
            'openRate30d' => 0.0,
            'optInCoverageRate' => 0.0,
            'averageInitialLtv' => 0.0,
            'averageFirstResponseMinutes' => 0.0,
            'responseRate30d' => 0.0,
            'repeatRevenue30d' => 0.0,
            'topAcquisitionChannel' => 'Sin datos',
            'topCustomerSegment' => 'Sin datos',
            'topLtvSegment' => 'Sin datos',
            'topLtvChannel' => 'Sin datos',
            'topLoyaltyChannel' => 'Sin datos',
            'topTemplateLabel' => 'Sin datos',
            'topClosingChannel' => 'Sin datos',
            'topReactivationSegment' => 'Sin datos',
            'scheduledCampaignsCount' => 0,
            'recurringCampaignsCount' => 0,
            'rewardCampaignsCount' => 0,
            'loyaltyRedeems30d' => 0,
            'currentCohortCustomersCount' => 0,
            'previousCohortCustomersCount' => 0,
            'cohortRepeatRate' => 0.0,
            'cohortLtv' => 0.0,
            'openCustomerTasksCount' => 0,
            'overdueCustomerTasksCount' => 0,
            'journeysReadyCount' => 0,
            'stalledJourneysCount' => 0,
            'campaignReadyCustomersCount' => 0,
            'assignedCustomersCount' => 0,
            'locatedCustomersCount' => 0,
        ];
    }

    private function formatCurrency(float $value): string
    {
        return 'L. '.number_format($value, 2);
    }

    private function formatSourceLabel(string $source): string
    {
        return match ($source) {
            'whatsapp_click' => 'WhatsApp',
            'catalog' => 'Catalogo',
            'website' => 'Sitio web',
            'manual' => 'Manual',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }

    private function formatOrderSourceLabel(string $source): string
    {
        return match ($source) {
            'web' => 'Web',
            'whatsapp' => 'WhatsApp',
            'direct' => 'Directo',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }

    private function ensureCustomerProfilesBootstrapped(string $tenantId): void
    {
        $profilesExist = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($profilesExist) {
            return;
        }

        $hasCustomerSignals = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
            })
            ->exists()
            || Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
                })
                ->exists()
            || Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('email')->orWhereNotNull('phone');
                })
                ->exists();

        if ($hasCustomerSignals) {
            $this->customerProfileManager->syncTenant($tenantId);
        }
    }

    /**
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function customerSnapshotFromOrders(string $tenantId, Carbon $since): array
    {
        $customerKey = "coalesce(nullif(lower(customer_email), ''), concat('phone:', nullif(customer_phone, '')), concat('guest:', id))";

        /** @var Collection<int, object> $rows */
        $rows = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->selectRaw("{$customerKey} as customer_key, count(*) as total_orders, max(created_at) as last_seen_at")
            ->groupByRaw($customerKey)
            ->get();

        return $this->aggregateCustomerSnapshot($rows);
    }

    /**
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function customerSnapshotFromAppointments(string $tenantId, Carbon $since): array
    {
        $customerKey = "coalesce(nullif(lower(customer_email), ''), concat('phone:', nullif(customer_phone, '')), concat('guest:', id))";

        /** @var Collection<int, object> $rows */
        $rows = Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->selectRaw("{$customerKey} as customer_key, count(*) as total_orders, max(created_at) as last_seen_at")
            ->groupByRaw($customerKey)
            ->get();

        return $this->aggregateCustomerSnapshot($rows);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function aggregateCustomerSnapshot(Collection $rows): array
    {
        $distinct = $rows->count();
        $repeat = $rows->filter(static fn (object $row): bool => (int) ($row->total_orders ?? 0) >= 2)->count();
        $reactivation = $rows->filter(function (object $row): bool {
            $lastSeen = $row->last_seen_at ?? null;

            if ($lastSeen === null) {
                return false;
            }

            return Carbon::parse((string) $lastSeen)->lt(now()->subDays(30));
        })->count();

        return [
            'distinct' => $distinct,
            'repeat' => $repeat,
            'reactivation' => $reactivation,
        ];
    }

    /**
     * @return array{topOfferLabel: string, topOfferCopy: string}
     */
    private function loadTopProductOffer(string $tenantId, Carbon $since): array
    {
        $topProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED])
            ->selectRaw('coalesce(order_items.product_name, "Producto") as offer_name, sum(order_items.quantity) as units, sum(coalesce(order_items.line_total, order_items.total_price, 0)) as revenue')
            ->groupBy('offer_name')
            ->orderByDesc('revenue')
            ->orderByDesc('units')
            ->first();

        if ($topProduct === null) {
            return [
                'topOfferLabel' => 'Sin datos',
                'topOfferCopy' => 'Todavia no hay suficiente actividad reciente para destacar una oferta.',
            ];
        }

        return [
            'topOfferLabel' => (string) data_get($topProduct, 'offer_name', 'Producto'),
            'topOfferCopy' => (int) data_get($topProduct, 'units', 0) > 0
                ? ((int) data_get($topProduct, 'units', 0)).' unidades movieron '.$this->formatCurrency((float) data_get($topProduct, 'revenue', 0)).' en los ultimos 30 dias.'
                : 'Tu catalogo ya muestra una oferta dominante.',
        ];
    }

    /**
     * @return array{topOfferLabel: string, topOfferCopy: string}
     */
    private function loadTopServiceOffer(string $tenantId, Carbon $since): array
    {
        $topService = Appointment::query()
            ->withoutGlobalScopes()
            ->leftJoin('services', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.tenant_id', $tenantId)
            ->where('appointments.created_at', '>=', $since)
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('coalesce(services.name, "Servicio") as offer_name, count(*) as units')
            ->groupBy('offer_name')
            ->orderByDesc('units')
            ->first();

        if ($topService === null || (int) data_get($topService, 'units', 0) <= 0) {
            return [
                'topOfferLabel' => 'Sin datos',
                'topOfferCopy' => 'Todavia no hay suficiente actividad reciente para destacar una oferta.',
            ];
        }

        return [
            'topOfferLabel' => (string) data_get($topService, 'offer_name', 'Servicio'),
            'topOfferCopy' => (int) data_get($topService, 'units', 0).' reservas recientes apuntan a esta oferta como la de mayor traccion.',
        ];
    }
}
