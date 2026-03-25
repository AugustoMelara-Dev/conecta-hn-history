<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Mail\CustomerLifecycleMail;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;

class CustomerAutomationManager
{
    public function __construct(
        private readonly CustomerMessagingGateway $messagingGateway,
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

        $requiresMarketingOptIn = (bool) ($options['requires_marketing_opt_in'] ?? false);
        $ctaLabel = $options['cta_label'] ?? null;
        $ctaUrl = $options['cta_url'] ?? $this->defaultCtaUrl($profile);

        $touchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $profile->tenant_id,
                'customer_profile_id' => (int) $profile->getKey(),
                'created_by_user_id' => $actorUserId,
                'channel' => 'email',
                'direction' => 'outbound',
                'automation_key' => $automationKey,
                'subject' => $subject,
                'message' => $body,
                'recipient_address' => $profile->email,
                'status' => CustomerTouchpoint::STATUS_QUEUED,
                'provider' => null,
                'provider_message_id' => null,
                'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
                'opt_in_snapshot' => (bool) $profile->email_opt_in,
                'metadata' => [
                    'cta_label' => $ctaLabel,
                    'cta_url' => $ctaUrl,
                    'requires_marketing_opt_in' => $requiresMarketingOptIn,
                    'segment' => $this->segmentKey($profile),
                ],
            ]);

        if (! filled($profile->email)) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene email.');
        }

        if (! $profile->email_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de email.');
        }

        if ($requiresMarketingOptIn && ! $profile->marketing_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de marketing.');
        }

        try {
            $mail = new CustomerLifecycleMail($subject, [
                'tenant_name' => (string) ($profile->tenant?->name ?? 'Conecta HN'),
                'customer_name' => (string) ($profile->name ?? 'cliente'),
                'intro' => (string) ($profile->tenant?->name ?? 'Conecta HN'),
                'body' => $body,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
            ]);

            $dispatch = $this->messagingGateway->sendLifecycleEmail($profile, $mail, [
                'automation_key' => $automationKey,
                'touchpoint_id' => (string) $touchpoint->getKey(),
                'segment' => $this->segmentKey($profile),
            ]);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_SENT,
                'provider' => $dispatch['provider'] ?? null,
                'provider_message_id' => $dispatch['provider_message_id'] ?: null,
                'delivery_status' => $dispatch['delivery_status'] ?: CustomerTouchpoint::DELIVERY_SENT,
                'sent_at' => now(),
                'last_error' => null,
                'provider_payload' => $dispatch['payload'] ?? null,
                'metadata' => array_merge($touchpoint->metadata ?? [], [
                    'transport' => $dispatch['payload'] ?? [],
                ]),
            ])->save();

            $profile->forceFill([
                'last_contacted_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return $touchpoint->fresh();
    }

    public function sendWhatsAppTouchpoint(
        CustomerProfile $profile,
        string $body,
        string $automationKey,
        ?string $actorUserId = null,
        array $options = [],
    ): CustomerTouchpoint {
        $profile->loadMissing('tenant');

        $requiresMarketingOptIn = (bool) ($options['requires_marketing_opt_in'] ?? false);
        $templateName = $options['template_name'] ?? null;
        $templateLanguage = $options['template_language'] ?? 'es_HN';
        $templateVariables = array_values((array) ($options['template_variables'] ?? []));
        $ctaUrl = $options['cta_url'] ?? $this->defaultCtaUrl($profile);

        $touchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $profile->tenant_id,
                'customer_profile_id' => (int) $profile->getKey(),
                'created_by_user_id' => $actorUserId,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'automation_key' => $automationKey,
                'subject' => null,
                'message' => $body,
                'recipient_address' => $profile->phone,
                'status' => CustomerTouchpoint::STATUS_QUEUED,
                'provider' => null,
                'provider_message_id' => null,
                'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
                'opt_in_snapshot' => (bool) $profile->whatsapp_opt_in,
                'metadata' => [
                    'cta_url' => $ctaUrl,
                    'requires_marketing_opt_in' => $requiresMarketingOptIn,
                    'segment' => $this->segmentKey($profile),
                    'template_name' => $templateName,
                    'template_language' => $templateLanguage,
                    'template_variables' => $templateVariables,
                ],
            ]);

        if (! filled($profile->phone)) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene telefono para WhatsApp.');
        }

        if (! $profile->whatsapp_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de WhatsApp.');
        }

        if ($requiresMarketingOptIn && ! $profile->marketing_opt_in) {
            return $this->markSkipped($touchpoint, 'El cliente no tiene opt-in de marketing.');
        }

        try {
            $dispatch = $this->messagingGateway->sendLifecycleWhatsApp($profile, [
                'body' => $body,
                'template_name' => $templateName,
                'template_language' => $templateLanguage,
                'template_variables' => $templateVariables,
            ], [
                'automation_key' => $automationKey,
                'touchpoint_id' => (string) $touchpoint->getKey(),
                'segment' => $this->segmentKey($profile),
                'cta_url' => $ctaUrl,
            ]);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_SENT,
                'provider' => $dispatch['provider'] ?? null,
                'provider_message_id' => $dispatch['provider_message_id'] ?: null,
                'delivery_status' => $dispatch['delivery_status'] ?: CustomerTouchpoint::DELIVERY_SENT,
                'sent_at' => now(),
                'last_error' => null,
                'provider_payload' => $dispatch['payload'] ?? null,
                'metadata' => array_merge($touchpoint->metadata ?? [], [
                    'transport' => $dispatch['payload'] ?? [],
                ]),
            ])->save();

            $profile->forceFill([
                'last_contacted_at' => now(),
                'last_engaged_channel' => 'whatsapp',
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            $touchpoint->forceFill([
                'status' => CustomerTouchpoint::STATUS_FAILED,
                'delivery_status' => CustomerTouchpoint::DELIVERY_FAILED,
                'failed_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
        }

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
     * @return array{subject: string, body: string, cta_label: string|null, cta_url: string|null, requires_marketing_opt_in: bool, channel: string, template_name: string|null, template_language: string|null, template_variables: array<int, string>}
     */
    public function preview(CustomerProfile $profile, string $automationKey = 'reactivation_email', ?string $channel = null): array
    {
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();
        $segmentKey = $this->segmentKey($profile, $tenant);
        $defaults = (array) config("customer-automation.defaults.{$automationKey}", []);
        $overrides = (array) config("customer-automation.profiles.{$segmentKey}.{$automationKey}", []);
        $definition = array_replace($defaults, $overrides);

        $ctaUrl = $this->defaultCtaUrl($profile, $tenant);
        $replace = [
            '{business_name}' => (string) ($tenant?->name ?? 'Conecta HN'),
            '{customer_name}' => (string) ($profile->name ?? 'cliente'),
            '{cta_url}' => $ctaUrl ?? '',
        ];

        return [
            'subject' => strtr((string) ($definition['subject'] ?? 'Seguimiento de {business_name}'), $replace),
            'body' => strtr((string) ($definition['body'] ?? ''), $replace),
            'cta_label' => isset($definition['cta_label']) ? (string) $definition['cta_label'] : null,
            'cta_url' => $ctaUrl,
            'requires_marketing_opt_in' => (bool) ($definition['requires_marketing_opt_in'] ?? false),
            'channel' => $channel ?? (string) ($definition['channel'] ?? 'email'),
            'template_name' => isset($definition['template_name']) ? (string) $definition['template_name'] : null,
            'template_language' => isset($definition['template_language']) ? (string) $definition['template_language'] : null,
            'template_variables' => array_values((array) ($definition['template_variables'] ?? [])),
        ];
    }

    private function markSkipped(CustomerTouchpoint $touchpoint, string $reason): CustomerTouchpoint
    {
        $touchpoint->forceFill([
            'status' => CustomerTouchpoint::STATUS_SKIPPED,
            'last_error' => $reason,
        ])->save();

        return $touchpoint->fresh();
    }

    private function defaultCtaUrl(CustomerProfile $profile, ?Tenant $tenant = null): ?string
    {
        $tenant ??= $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        if ($tenant->business_type?->value === 'service') {
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

        return (string) ($tenant->business_segment_key ?: app(BusinessSegmentResolver::class)->resolveForTenant($tenant));
    }
}
