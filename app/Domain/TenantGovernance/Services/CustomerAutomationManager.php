<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Mail\CustomerLifecycleMail;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class CustomerAutomationManager
{
    public function __construct(
        private readonly CustomerMessagingGateway $messagingGateway,
        private readonly CustomerConversationOpsManager $conversationOpsManager,
        private readonly CustomerTouchpointGovernance $governance,
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
    ) {}

    public function sendEmailTouchpoint(
        CustomerProfile $profile,
        string $subject,
        string $body,
        string $automationKey = 'manual_follow_up',
        ?string $actorUserId = null,
        array $options = [],
    ): CustomerTouchpoint {
        $profile->loadMissing('tenant');
        $conversationKey = isset($options['conversation_key']) && is_string($options['conversation_key']) && $options['conversation_key'] !== ''
            ? $options['conversation_key']
            : null;
        $providerThreadKey = isset($options['provider_thread_key']) && is_string($options['provider_thread_key']) && $options['provider_thread_key'] !== ''
            ? $options['provider_thread_key']
            : $conversationKey;
        $providerReplyTo = isset($options['provider_reply_to']) && is_string($options['provider_reply_to']) && $options['provider_reply_to'] !== ''
            ? $options['provider_reply_to']
            : null;

        $governance = $this->governance->definition($automationKey, 'email', $options);
        $attributes = [
            'tenant_id' => (string) $profile->tenant_id,
            'customer_profile_id' => (int) $profile->getKey(),
            'created_by_user_id' => $actorUserId,
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => $automationKey,
            'template_key' => $governance['template_key'],
            'subject' => $subject,
            'message' => $body,
            'recipient_address' => $profile->email,
            'status' => CustomerTouchpoint::STATUS_QUEUED,
            'provider' => null,
            'provider_message_id' => null,
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
            'attempt_count' => 0,
            'retry_limit' => $governance['retry_limit'],
            'opt_in_snapshot' => (bool) $profile->email_opt_in,
            'metadata' => $this->touchpointMetadata($profile, array_merge($options, [
                'conversation_key' => $conversationKey,
                'provider_thread_key' => $providerThreadKey,
                'provider_reply_to' => $providerReplyTo,
            ]), $governance),
        ];
        $attributes['campaign_id'] = $options['campaign_id'] ?? null;
        $attributes['campaign_run_key'] = $options['campaign_run_key'] ?? null;

        $touchpoint = $this->createOutboundTouchpoint($attributes);

        if (in_array($touchpoint->status, [CustomerTouchpoint::STATUS_SENT, CustomerTouchpoint::STATUS_SKIPPED], true)) {
            return $touchpoint;
        }

        return $this->dispatchEmailTouchpoint($touchpoint, $profile, $subject, $body, $actorUserId, $options);
    }

    public function sendWhatsAppTouchpoint(
        CustomerProfile $profile,
        string $body,
        string $automationKey,
        ?string $actorUserId = null,
        array $options = [],
    ): CustomerTouchpoint {
        $profile->loadMissing('tenant');
        $conversationKey = isset($options['conversation_key']) && is_string($options['conversation_key']) && $options['conversation_key'] !== ''
            ? $options['conversation_key']
            : null;
        $providerThreadKey = isset($options['provider_thread_key']) && is_string($options['provider_thread_key']) && $options['provider_thread_key'] !== ''
            ? $options['provider_thread_key']
            : $conversationKey;
        $providerReplyTo = isset($options['provider_reply_to']) && is_string($options['provider_reply_to']) && $options['provider_reply_to'] !== ''
            ? $options['provider_reply_to']
            : null;

        $governance = $this->governance->definition($automationKey, 'whatsapp', $options);
        $attributes = [
            'tenant_id' => (string) $profile->tenant_id,
            'customer_profile_id' => (int) $profile->getKey(),
            'created_by_user_id' => $actorUserId,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'automation_key' => $automationKey,
            'template_key' => $governance['template_key'],
            'subject' => null,
            'message' => $body,
            'recipient_address' => $profile->phone,
            'status' => CustomerTouchpoint::STATUS_QUEUED,
            'provider' => null,
            'provider_message_id' => null,
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
            'attempt_count' => 0,
            'retry_limit' => $governance['retry_limit'],
            'opt_in_snapshot' => (bool) $profile->whatsapp_opt_in,
            'metadata' => $this->touchpointMetadata($profile, array_merge($options, [
                'conversation_key' => $conversationKey,
                'provider_thread_key' => $providerThreadKey,
                'provider_reply_to' => $providerReplyTo,
            ]), $governance),
        ];
        $attributes['campaign_id'] = $options['campaign_id'] ?? null;
        $attributes['campaign_run_key'] = $options['campaign_run_key'] ?? null;

        $touchpoint = $this->createOutboundTouchpoint($attributes);

        if (in_array($touchpoint->status, [CustomerTouchpoint::STATUS_SENT, CustomerTouchpoint::STATUS_SKIPPED], true)) {
            return $touchpoint;
        }

        return $this->dispatchWhatsAppTouchpoint($touchpoint, $profile, $body, $actorUserId, $options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOutboundTouchpoint(array $attributes): CustomerTouchpoint
    {
        if (! empty($attributes['campaign_id']) && ! empty($attributes['campaign_run_key'])) {
            return CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->firstOrCreate([
                    'tenant_id' => $attributes['tenant_id'],
                    'campaign_id' => $attributes['campaign_id'],
                    'campaign_run_key' => $attributes['campaign_run_key'],
                    'customer_profile_id' => $attributes['customer_profile_id'],
                ], $attributes);
        }

        return $this->touchpoints->create($attributes);
    }

    public function retryTouchpoint(CustomerTouchpoint $touchpoint, ?string $actorUserId = null): CustomerTouchpoint
    {
        /** @var CustomerTouchpoint|null $record */
        $record = $this->touchpoints->findWithProfile((int) $touchpoint->getKey());

        if (! $record instanceof CustomerTouchpoint) {
            return $touchpoint;
        }

        $profile = $record->customerProfile;

        if (! $profile instanceof CustomerProfile || ! $this->governance->canRetry($record)) {
            return $record->fresh() ?? $record;
        }

        $options = $this->retryOptionsFromTouchpoint($record);
        $actorUserId ??= $record->created_by_user_id !== null ? (string) $record->created_by_user_id : null;

        if ($record->channel === 'whatsapp') {
            return $this->dispatchWhatsAppTouchpoint(
                $record,
                $profile,
                (string) $record->message,
                $actorUserId,
                $options,
                enforceDailyLimit: false,
            );
        }

        $tenant = $profile->tenant;
        $tenantName = $tenant instanceof Tenant ? (string) $tenant->name : 'Vito Business OS';

        return $this->dispatchEmailTouchpoint(
            $record,
            $profile,
            (string) ($record->subject ?: 'Seguimiento de '.$tenantName),
            (string) $record->message,
            $actorUserId,
            $options,
            enforceDailyLimit: false,
        );
    }

    public function scheduleRetry(CustomerTouchpoint $touchpoint, ?Carbon $baseTime = null): CustomerTouchpoint
    {
        $nextRetryAt = $this->governance->nextRetryAt($touchpoint, $baseTime);

        $touchpoint->forceFill([
            'next_retry_at' => $nextRetryAt,
        ]);

        $this->touchpoints->save($touchpoint);

        return $touchpoint->fresh();
    }

    public function clearRetry(CustomerTouchpoint $touchpoint): CustomerTouchpoint
    {
        $touchpoint->forceFill([
            'next_retry_at' => null,
        ]);

        $this->touchpoints->save($touchpoint);

        return $touchpoint->fresh();
    }

    public function sendSuggestedAutomation(
        CustomerProfile $profile,
        string $automationKey = 'reactivation_email',
        ?string $actorUserId = null,
        ?string $channel = null,
    ): CustomerTouchpoint {
        $template = $this->preview($profile, $automationKey, $channel);

        if ($template['channel'] === 'whatsapp') {
            return $this->sendWhatsAppTouchpoint(
                profile: $profile,
                body: $template['body'],
                automationKey: $automationKey,
                actorUserId: $actorUserId,
                options: [
                    'cta_url' => $template['cta_url'],
                    'template_name' => $template['template_name'],
                    'template_language' => $template['template_language'],
                    'template_variables' => $template['template_variables'],
                    'template_key' => $template['template_key'],
                    'daily_limit' => $template['daily_limit'],
                    'retry_limit' => $template['retry_limit'],
                    'retry_backoff_minutes' => $template['retry_backoff_minutes'],
                    'requires_marketing_opt_in' => $template['requires_marketing_opt_in'],
                ],
            );
        }

        return $this->sendEmailTouchpoint(
            profile: $profile,
            subject: $template['subject'],
            body: $template['body'],
            automationKey: $automationKey,
            actorUserId: $actorUserId,
            options: [
                'cta_label' => $template['cta_label'],
                'cta_url' => $template['cta_url'],
                'template_key' => $template['template_key'],
                'daily_limit' => $template['daily_limit'],
                'retry_limit' => $template['retry_limit'],
                'retry_backoff_minutes' => $template['retry_backoff_minutes'],
                'requires_marketing_opt_in' => $template['requires_marketing_opt_in'],
            ],
        );
    }

    /**
     * @return array{processed: int, sent: int, skipped: int, failed: int}
     */
    public function runSuggestedAutomationBatch(
        Tenant|string $tenant,
        string $automationKey = 'reactivation_email',
        ?string $actorUserId = null,
        int $limit = 25,
    ): array {
        $tenantModel = $tenant instanceof Tenant
            ? $tenant
            : Tenant::query()->withoutGlobalScopes()->findOrFail($tenant);

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $profiles = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantModel->getKey())
            ->whereNotNull('email')
            ->where('reactivation_score', '>=', 65)
            ->orderByDesc('reactivation_score')
            ->orderByDesc('last_seen_at')
            ->limit(max(1, $limit))
            ->get();

        foreach ($profiles as $profile) {
            $processed++;
            $touchpoint = $this->sendSuggestedAutomation($profile, $automationKey, $actorUserId);

            $sent += $touchpoint->status === CustomerTouchpoint::STATUS_SENT ? 1 : 0;
            $skipped += $touchpoint->status === CustomerTouchpoint::STATUS_SKIPPED ? 1 : 0;
            $failed += $touchpoint->status === CustomerTouchpoint::STATUS_FAILED ? 1 : 0;
        }

        return compact('processed', 'sent', 'skipped', 'failed');
    }

    /**
     * @return array{subject: string, body: string, cta_label: string|null, cta_url: string|null, requires_marketing_opt_in: bool, channel: string, template_name: string|null, template_language: string|null, template_variables: array<int, string>, template_key: string, daily_limit: int, retry_limit: int, retry_backoff_minutes: int}
     */
    public function preview(CustomerProfile $profile, string $automationKey = 'reactivation_email', ?string $channel = null): array
    {
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;
        $segmentKey = $this->segmentKey($profile, $tenant);
        $defaults = (array) config("customer-automation.defaults.{$automationKey}", []);
        $overrides = (array) config("customer-automation.profiles.{$segmentKey}.{$automationKey}", []);
        $manifestOverrides = $tenant instanceof Tenant
            ? (array) data_get(app(RubroManifestReaderInterface::class)->crmForTenant($tenant), "automation.{$automationKey}", [])
            : [];
        $definition = array_replace($defaults, $overrides, $manifestOverrides);

        $resolvedChannel = $channel ?? (string) ($definition['channel'] ?? 'email');
        $governance = $this->governance->definition($automationKey, $resolvedChannel, $definition);
        $ctaUrl = $this->defaultCtaUrl($profile, $tenant);
        $replace = [
            '{business_name}' => (string) ($tenant->name ?? 'Vito Business OS'),
            '{customer_name}' => (string) ($profile->name ?? 'cliente'),
            '{cta_url}' => $ctaUrl ?? '',
        ];

        return [
            'subject' => strtr((string) ($definition['subject'] ?? 'Seguimiento de {business_name}'), $replace),
            'body' => strtr((string) ($definition['body'] ?? ''), $replace),
            'cta_label' => isset($definition['cta_label']) ? (string) $definition['cta_label'] : null,
            'cta_url' => $ctaUrl,
            'requires_marketing_opt_in' => (bool) ($definition['requires_marketing_opt_in'] ?? false),
            'channel' => $resolvedChannel,
            'template_name' => isset($definition['template_name']) ? (string) $definition['template_name'] : null,
            'template_language' => isset($definition['template_language']) ? (string) $definition['template_language'] : null,
            'template_variables' => array_values((array) ($definition['template_variables'] ?? [])),
            'template_key' => $governance['template_key'],
            'daily_limit' => $governance['daily_limit'],
            'retry_limit' => $governance['retry_limit'],
            'retry_backoff_minutes' => $governance['retry_backoff_minutes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function dispatchEmailTouchpoint(
        CustomerTouchpoint $touchpoint,
        CustomerProfile $profile,
        string $subject,
        string $body,
        ?string $actorUserId,
        array $options,
        bool $enforceDailyLimit = true,
    ): CustomerTouchpoint {
        $governance = $this->governance->definition((string) ($touchpoint->automation_key ?? 'manual_follow_up'), 'email', array_merge([
            'template_key' => $touchpoint->template_key,
            'retry_limit' => $touchpoint->retry_limit,
        ], $options, [
            'daily_limit' => data_get($touchpoint->metadata, 'daily_limit'),
            'retry_backoff_minutes' => data_get($touchpoint->metadata, 'retry_backoff_minutes'),
        ]));

        if (! filled($profile->email)) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene email.');
        }

        if (! $profile->email_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de email.');
        }

        if ((bool) ($options['requires_marketing_opt_in'] ?? data_get($touchpoint->metadata, 'requires_marketing_opt_in', false))
            && ! $profile->marketing_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de marketing.');
        }

        if ($enforceDailyLimit && $this->governance->hasReachedDailyLimit(
            $profile,
            'email',
            $governance['template_key'],
            $governance['daily_limit'],
            (int) $touchpoint->getKey(),
        )) {
            return $this->markSkipped($touchpoint, 'El cliente ya recibio este template hoy.');
        }

        $touchpoint->forceFill([
            'created_by_user_id' => $actorUserId ?? $touchpoint->created_by_user_id,
            'subject' => $subject,
            'message' => $body,
            'recipient_address' => $profile->email,
            'template_key' => $governance['template_key'],
            'retry_limit' => $governance['retry_limit'],
            'metadata' => array_merge($touchpoint->metadata ?? [], [
                'template_key' => $governance['template_key'],
                'daily_limit' => $governance['daily_limit'],
                'retry_limit' => $governance['retry_limit'],
                'retry_backoff_minutes' => $governance['retry_backoff_minutes'],
            ]),
        ])->save();

        $this->prepareAttempt($touchpoint);

        try {
            $tenant = $profile->tenant;
            $tenantName = $tenant instanceof Tenant ? (string) $tenant->name : 'Vito Business OS';
            $mail = new CustomerLifecycleMail($subject, [
                'tenant_name' => $tenantName,
                'customer_name' => (string) ($profile->name ?? 'cliente'),
                'intro' => $tenantName,
                'body' => $body,
                'cta_label' => $options['cta_label'] ?? data_get($touchpoint->metadata, 'cta_label'),
                'cta_url' => $options['cta_url'] ?? $this->defaultCtaUrl($profile),
            ]);

            $dispatch = $this->messagingGateway->sendLifecycleEmail($profile, $mail, [
                'automation_key' => $touchpoint->automation_key,
                'touchpoint_id' => (string) $touchpoint->getKey(),
                'segment' => $this->segmentKey($profile),
                'conversation_key' => $options['conversation_key'] ?? data_get($touchpoint->metadata, 'conversation_key'),
                'provider_thread_key' => $options['provider_thread_key'] ?? data_get($touchpoint->metadata, 'provider_thread_key', data_get($touchpoint->metadata, 'conversation_key')),
                'provider_reply_to' => $options['provider_reply_to'] ?? data_get($touchpoint->metadata, 'provider_reply_to'),
                'source_note_id' => $options['source_note_id'] ?? data_get($touchpoint->metadata, 'source_note_id'),
                'source_channel' => $options['source_channel'] ?? data_get($touchpoint->metadata, 'source_channel'),
                'campaign_id' => $options['campaign_id'] ?? data_get($touchpoint->metadata, 'campaign_id'),
                'campaign_subject' => $options['campaign_subject'] ?? data_get($touchpoint->metadata, 'campaign_subject'),
                'reward_name' => $options['reward_name'] ?? data_get($touchpoint->metadata, 'reward_name'),
                'reward_points_cost' => $options['reward_points_cost'] ?? data_get($touchpoint->metadata, 'reward_points_cost'),
                'cadence_interval_days' => $options['cadence_interval_days'] ?? data_get($touchpoint->metadata, 'cadence_interval_days'),
            ]);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_SENT,
                'provider' => $dispatch['provider'],
                'provider_message_id' => $dispatch['provider_message_id'] ?: null,
                'delivery_status' => $dispatch['delivery_status'] ?: CustomerTouchpoint::DELIVERY_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'next_retry_at' => null,
                'last_error' => null,
                'provider_payload' => $dispatch['payload'],
                'metadata' => array_merge($touchpoint->metadata ?? [], [
                    'transport' => $dispatch['payload'],
                    'provider_thread_key' => $options['provider_thread_key']
                        ?? data_get($touchpoint->metadata, 'provider_thread_key')
                        ?? data_get($touchpoint->metadata, 'conversation_key'),
                    'provider_reply_to' => $options['provider_reply_to'] ?? data_get($touchpoint->metadata, 'provider_reply_to'),
                ]),
            ])->save();

            $profile->forceFill([
                'last_contacted_at' => now(),
                'last_engaged_channel' => 'email',
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->markFailed($touchpoint, $exception->getMessage(), CustomerTouchpoint::DELIVERY_FAILED);
        }

        $this->syncConversationAfterDispatch($profile, $touchpoint, $actorUserId);

        return $touchpoint->fresh();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function dispatchWhatsAppTouchpoint(
        CustomerTouchpoint $touchpoint,
        CustomerProfile $profile,
        string $body,
        ?string $actorUserId,
        array $options,
        bool $enforceDailyLimit = true,
    ): CustomerTouchpoint {
        $governance = $this->governance->definition((string) ($touchpoint->automation_key ?? 'manual_follow_up_whatsapp'), 'whatsapp', array_merge([
            'template_key' => $touchpoint->template_key,
            'retry_limit' => $touchpoint->retry_limit,
        ], $options, [
            'daily_limit' => data_get($touchpoint->metadata, 'daily_limit'),
            'retry_backoff_minutes' => data_get($touchpoint->metadata, 'retry_backoff_minutes'),
        ]));

        if (! filled($profile->phone)) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene tel�fono para WhatsApp.');
        }

        if (! $profile->whatsapp_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de WhatsApp.');
        }

        if ((bool) ($options['requires_marketing_opt_in'] ?? data_get($touchpoint->metadata, 'requires_marketing_opt_in', false))
            && ! $profile->marketing_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de marketing.');
        }

        if ($enforceDailyLimit && $this->governance->hasReachedDailyLimit(
            $profile,
            'whatsapp',
            $governance['template_key'],
            $governance['daily_limit'],
            (int) $touchpoint->getKey(),
        )) {
            return $this->markSkipped($touchpoint, 'El cliente ya recibio este template hoy.');
        }

        $touchpoint->forceFill([
            'created_by_user_id' => $actorUserId ?? $touchpoint->created_by_user_id,
            'message' => $body,
            'recipient_address' => $profile->phone,
            'template_key' => $governance['template_key'],
            'retry_limit' => $governance['retry_limit'],
            'metadata' => array_merge($touchpoint->metadata ?? [], [
                'template_name' => $options['template_name'] ?? data_get($touchpoint->metadata, 'template_name'),
                'template_language' => $options['template_language'] ?? data_get($touchpoint->metadata, 'template_language', 'es_HN'),
                'template_variables' => array_values((array) ($options['template_variables'] ?? data_get($touchpoint->metadata, 'template_variables', []))),
                'template_key' => $governance['template_key'],
                'daily_limit' => $governance['daily_limit'],
                'retry_limit' => $governance['retry_limit'],
                'retry_backoff_minutes' => $governance['retry_backoff_minutes'],
            ]),
        ])->save();

        $this->prepareAttempt($touchpoint);

        try {
            $dispatch = $this->messagingGateway->sendLifecycleWhatsApp($profile, [
                'body' => $body,
                'template_name' => $options['template_name'] ?? data_get($touchpoint->metadata, 'template_name'),
                'template_language' => $options['template_language'] ?? data_get($touchpoint->metadata, 'template_language', 'es_HN'),
                'template_variables' => array_values((array) ($options['template_variables'] ?? data_get($touchpoint->metadata, 'template_variables', []))),
            ], [
                'automation_key' => $touchpoint->automation_key,
                'touchpoint_id' => (string) $touchpoint->getKey(),
                'segment' => $this->segmentKey($profile),
                'conversation_key' => $options['conversation_key'] ?? data_get($touchpoint->metadata, 'conversation_key'),
                'provider_thread_key' => $options['provider_thread_key'] ?? data_get($touchpoint->metadata, 'provider_thread_key', data_get($touchpoint->metadata, 'conversation_key')),
                'provider_reply_to' => $options['provider_reply_to'] ?? data_get($touchpoint->metadata, 'provider_reply_to'),
                'source_note_id' => $options['source_note_id'] ?? data_get($touchpoint->metadata, 'source_note_id'),
                'source_channel' => $options['source_channel'] ?? data_get($touchpoint->metadata, 'source_channel'),
                'cta_url' => $options['cta_url'] ?? data_get($touchpoint->metadata, 'cta_url'),
                'cta_label' => $options['cta_label'] ?? data_get($touchpoint->metadata, 'cta_label'),
                'campaign_id' => $options['campaign_id'] ?? data_get($touchpoint->metadata, 'campaign_id'),
                'campaign_subject' => $options['campaign_subject'] ?? data_get($touchpoint->metadata, 'campaign_subject'),
                'reward_name' => $options['reward_name'] ?? data_get($touchpoint->metadata, 'reward_name'),
                'reward_points_cost' => $options['reward_points_cost'] ?? data_get($touchpoint->metadata, 'reward_points_cost'),
                'cadence_interval_days' => $options['cadence_interval_days'] ?? data_get($touchpoint->metadata, 'cadence_interval_days'),
            ]);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_SENT,
                'provider' => $dispatch['provider'],
                'provider_message_id' => $dispatch['provider_message_id'] ?: null,
                'delivery_status' => $dispatch['delivery_status'] ?: CustomerTouchpoint::DELIVERY_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'next_retry_at' => null,
                'last_error' => null,
                'provider_payload' => $dispatch['payload'],
                'metadata' => array_merge($touchpoint->metadata ?? [], [
                    'transport' => $dispatch['payload'],
                    'provider_thread_key' => $options['provider_thread_key']
                        ?? data_get($touchpoint->metadata, 'provider_thread_key')
                        ?? data_get($touchpoint->metadata, 'conversation_key'),
                    'provider_reply_to' => $options['provider_reply_to'] ?? data_get($touchpoint->metadata, 'provider_reply_to'),
                ]),
            ])->save();

            $profile->forceFill([
                'last_contacted_at' => now(),
                'last_engaged_channel' => 'whatsapp',
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->markFailed($touchpoint, $exception->getMessage(), CustomerTouchpoint::DELIVERY_FAILED);
        }

        $this->syncConversationAfterDispatch($profile, $touchpoint, $actorUserId);

        return $touchpoint->fresh();
    }

    private function syncConversationAfterDispatch(CustomerProfile $profile, CustomerTouchpoint $touchpoint, ?string $actorUserId): void
    {
        try {
            $this->conversationOpsManager->markOutboundResponse($profile, $touchpoint, $actorUserId);
        } catch (\Throwable $exception) {
            report($exception);

            $syncWarnings = array_values((array) data_get($touchpoint->metadata, 'sync_warnings', []));
            $syncWarnings[] = [
                'scope' => 'conversation',
                'message' => $exception->getMessage(),
                'recorded_at' => now()->toIso8601String(),
            ];

            $touchpoint->forceFill([
                'metadata' => array_merge((array) $touchpoint->metadata, [
                    'sync_warnings' => $syncWarnings,
                ]),
            ])->save();
        }
    }

    private function prepareAttempt(CustomerTouchpoint $touchpoint): void
    {
        $touchpoint->forceFill([
            'status' => CustomerTouchpoint::STATUS_QUEUED,
            'last_attempt_at' => now(),
            'attempt_count' => ((int) $touchpoint->attempt_count) + 1,
            'next_retry_at' => null,
        ])->save();
    }

    private function markSkipped(CustomerTouchpoint $touchpoint, string $reason): CustomerTouchpoint
    {
        $touchpoint->forceFill([
            'status' => CustomerTouchpoint::STATUS_SKIPPED,
            'next_retry_at' => null,
            'last_error' => $reason,
        ])->save();

        return $touchpoint->fresh();
    }

    private function markFailed(
        CustomerTouchpoint $touchpoint,
        string $reason,
        ?string $deliveryStatus = null,
        ?Carbon $failedAt = null,
        bool $scheduleRetry = true,
    ): CustomerTouchpoint {
        $failedAt ??= now();

        $touchpoint->forceFill([
            'status' => CustomerTouchpoint::STATUS_FAILED,
            'delivery_status' => $deliveryStatus ?? CustomerTouchpoint::DELIVERY_FAILED,
            'failed_at' => $failedAt,
            'last_error' => $reason,
            'next_retry_at' => $scheduleRetry ? $this->governance->nextRetryAt($touchpoint, $failedAt) : null,
        ])->save();

        return $touchpoint->fresh();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{template_key: string, daily_limit: int, retry_limit: int, retry_backoff_minutes: int}  $governance
     * @return array<string, mixed>
     */
    private function touchpointMetadata(CustomerProfile $profile, array $options, array $governance): array
    {
        return [
            'cta_label' => $options['cta_label'] ?? null,
            'cta_url' => $options['cta_url'] ?? $this->defaultCtaUrl($profile),
            'requires_marketing_opt_in' => (bool) ($options['requires_marketing_opt_in'] ?? false),
            'segment' => $this->segmentKey($profile),
            'conversation_key' => $options['conversation_key'] ?? null,
            'provider_thread_key' => $options['provider_thread_key'] ?? ($options['conversation_key'] ?? null),
            'provider_reply_to' => $options['provider_reply_to'] ?? null,
            'source_note_id' => $options['source_note_id'] ?? null,
            'source_channel' => $options['source_channel'] ?? null,
            'journey_key' => $options['journey_key'] ?? null,
            'journey_lane' => $options['journey_lane'] ?? null,
            'journey_step_key' => $options['journey_step_key'] ?? null,
            'journey_step_title' => $options['journey_step_title'] ?? null,
            'journey_branch_key' => $options['journey_branch_key'] ?? null,
            'journey_branch_label' => $options['journey_branch_label'] ?? null,
            'journey_sequence_key' => $options['journey_sequence_key'] ?? null,
            'journey_sequence_label' => $options['journey_sequence_label'] ?? null,
            'journey_template_key' => $options['journey_template_key'] ?? null,
            'campaign_id' => $options['campaign_id'] ?? null,
            'campaign_subject' => $options['campaign_subject'] ?? null,
            'reward_name' => $options['reward_name'] ?? null,
            'reward_points_cost' => $options['reward_points_cost'] ?? null,
            'cadence_interval_days' => $options['cadence_interval_days'] ?? null,
            'template_name' => $options['template_name'] ?? null,
            'template_language' => $options['template_language'] ?? null,
            'template_variables' => array_values((array) ($options['template_variables'] ?? [])),
            'template_key' => $governance['template_key'],
            'daily_limit' => $governance['daily_limit'],
            'retry_limit' => $governance['retry_limit'],
            'retry_backoff_minutes' => $governance['retry_backoff_minutes'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function retryOptionsFromTouchpoint(CustomerTouchpoint $touchpoint): array
    {
        return [
            'cta_label' => data_get($touchpoint->metadata, 'cta_label'),
            'cta_url' => data_get($touchpoint->metadata, 'cta_url'),
            'requires_marketing_opt_in' => (bool) data_get($touchpoint->metadata, 'requires_marketing_opt_in', false),
            'conversation_key' => data_get($touchpoint->metadata, 'conversation_key'),
            'provider_thread_key' => data_get($touchpoint->metadata, 'provider_thread_key', data_get($touchpoint->metadata, 'conversation_key')),
            'provider_reply_to' => data_get($touchpoint->metadata, 'provider_reply_to'),
            'source_note_id' => data_get($touchpoint->metadata, 'source_note_id'),
            'source_channel' => data_get($touchpoint->metadata, 'source_channel'),
            'journey_key' => data_get($touchpoint->metadata, 'journey_key'),
            'journey_lane' => data_get($touchpoint->metadata, 'journey_lane'),
            'journey_step_key' => data_get($touchpoint->metadata, 'journey_step_key'),
            'journey_step_title' => data_get($touchpoint->metadata, 'journey_step_title'),
            'journey_branch_key' => data_get($touchpoint->metadata, 'journey_branch_key'),
            'journey_branch_label' => data_get($touchpoint->metadata, 'journey_branch_label'),
            'journey_sequence_key' => data_get($touchpoint->metadata, 'journey_sequence_key'),
            'journey_sequence_label' => data_get($touchpoint->metadata, 'journey_sequence_label'),
            'journey_template_key' => data_get($touchpoint->metadata, 'journey_template_key'),
            'campaign_id' => data_get($touchpoint->metadata, 'campaign_id'),
            'campaign_subject' => data_get($touchpoint->metadata, 'campaign_subject'),
            'reward_name' => data_get($touchpoint->metadata, 'reward_name'),
            'reward_points_cost' => data_get($touchpoint->metadata, 'reward_points_cost'),
            'cadence_interval_days' => data_get($touchpoint->metadata, 'cadence_interval_days'),
            'template_name' => data_get($touchpoint->metadata, 'template_name'),
            'template_language' => data_get($touchpoint->metadata, 'template_language', 'es_HN'),
            'template_variables' => array_values((array) data_get($touchpoint->metadata, 'template_variables', [])),
            'template_key' => $touchpoint->template_key ?: data_get($touchpoint->metadata, 'template_key'),
            'daily_limit' => data_get($touchpoint->metadata, 'daily_limit'),
            'retry_limit' => $touchpoint->retry_limit,
            'retry_backoff_minutes' => data_get($touchpoint->metadata, 'retry_backoff_minutes'),
        ];
    }

    private function defaultCtaUrl(CustomerProfile $profile, ?Tenant $tenant = null): ?string
    {
        $tenant ??= $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        if ($tenant->business_type->value === 'service') {
            return route('public.booking.index', ['tenant' => $tenant->slug]);
        }

        return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
    }

    private function segmentKey(CustomerProfile $profile, ?Tenant $tenant = null): string
    {
        $tenant ??= $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return 'retail_growth';
        }

        if (filled($tenant->rubro_key)) {
            return (string) $tenant->rubro_key;
        }

        return (string) ($tenant->business_segment_key ?: app(BusinessSegmentResolver::class)->resolveForTenant($tenant));
    }
}
