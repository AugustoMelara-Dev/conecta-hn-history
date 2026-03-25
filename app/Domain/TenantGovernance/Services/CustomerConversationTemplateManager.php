<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;

class CustomerConversationTemplateManager
{
    public function __construct(
        private readonly CustomerAutomationManager $automationManager,
    ) {}

    /**
     * @return array{
     *     segment_key:string,
     *     journey:list<array{title:string,copy:string}>,
     *     templates:list<array{
     *         key:string,
     *         label:string,
     *         description:string,
     *         channel:string,
     *         channel_label:string,
     *         automation_key:string,
     *         available:bool,
     *         warning:?string,
     *         requires_marketing_opt_in:bool
     *     }>
     * }
     */
    public function forConversation(CustomerProfile $profile, ?CustomerNote $note = null): array
    {
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $segmentDefinition = (array) config("customer-conversations.segments.{$segmentKey}", []);
        $templates = collect((array) ($segmentDefinition['templates'] ?? []))
            ->map(fn (mixed $templateKey): array => $this->templateDefinition(
                $profile,
                is_string($templateKey) ? $templateKey : 'fast_follow_up',
                $note,
            ))
            ->values()
            ->all();

        return [
            'segment_key' => $segmentKey,
            'journey' => array_values((array) ($segmentDefinition['journey'] ?? [])),
            'templates' => $templates,
        ];
    }

    public function send(CustomerProfile $profile, string $templateKey, ?CustomerNote $note = null, ?string $actorUserId = null): CustomerTouchpoint
    {
        $definition = $this->templateDefinition($profile, $templateKey, $note);

        if (! $definition['available']) {
            throw new \RuntimeException((string) ($definition['warning'] ?? 'La plantilla no se puede enviar en este momento.'));
        }

        $channel = $definition['channel'];
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();

        $options = [
            'cta_label' => $definition['cta_label'],
            'cta_url' => $this->defaultCtaUrl($profile, $tenant),
            'template_key' => $templateKey,
            'template_name' => $definition['template_name'],
            'template_language' => $definition['template_language'],
            'template_variables' => $definition['template_variables'],
            'daily_limit' => $definition['daily_limit'],
            'retry_limit' => $definition['retry_limit'],
            'requires_marketing_opt_in' => $definition['requires_marketing_opt_in'],
            'conversation_key' => $note?->conversation_key,
            'source_note_id' => $note?->getKey(),
            'source_channel' => $note?->source_channel,
            'journey_template_key' => $templateKey,
        ];

        if ($channel === 'whatsapp') {
            return $this->automationManager->sendWhatsAppTouchpoint(
                profile: $profile,
                body: $definition['body'],
                automationKey: $definition['automation_key'],
                actorUserId: $actorUserId,
                options: $options,
            );
        }

        return $this->automationManager->sendEmailTouchpoint(
            profile: $profile,
            subject: $definition['subject'],
            body: $definition['body'],
            automationKey: $definition['automation_key'],
            actorUserId: $actorUserId,
            options: $options,
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     channel:string,
     *     channel_label:string,
     *     automation_key:string,
     *     subject:string,
     *     body:string,
     *     cta_label:?string,
     *     template_name:?string,
     *     template_language:?string,
     *     template_variables:array<int,string>,
     *     daily_limit:int,
     *     retry_limit:int,
     *     requires_marketing_opt_in:bool,
     *     available:bool,
     *     warning:?string
     * }
     */
    public function templateDefinition(CustomerProfile $profile, string $templateKey, ?CustomerNote $note = null): array
    {
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();
        $definition = (array) config("customer-conversations.templates.{$templateKey}", []);
        $channel = $this->resolveChannel($profile, (string) ($definition['channel'] ?? 'preferred'));
        $replace = [
            '{business_name}' => (string) ($tenant?->name ?? 'Conecta HN'),
            '{customer_name}' => (string) ($profile->name ?? 'cliente'),
            '{cta_url}' => (string) ($this->defaultCtaUrl($profile, $tenant) ?? ''),
        ];
        $warning = $this->availabilityWarning($profile, $channel, (bool) ($definition['requires_marketing_opt_in'] ?? false));

        if ($warning === null && $note instanceof CustomerNote && $note->status === 'resolved') {
            $warning = 'La conversacion ya esta resuelta. Reabre el caso si quieres seguir el hilo operativo.';
        }

        return [
            'key' => $templateKey,
            'label' => (string) ($definition['label'] ?? 'Plantilla sugerida'),
            'description' => (string) ($definition['description'] ?? 'Respuesta guiada por segmento.'),
            'channel' => $channel,
            'channel_label' => $channel === 'whatsapp' ? 'WhatsApp' : 'Email',
            'automation_key' => (string) ($definition['automation_key'] ?? 'manual_follow_up'),
            'subject' => strtr((string) ($definition['subject'] ?? 'Seguimiento de {business_name}'), $replace),
            'body' => strtr((string) ($definition['body'] ?? ''), $replace),
            'cta_label' => isset($definition['cta_label']) ? (string) $definition['cta_label'] : null,
            'template_name' => isset($definition['template_name']) ? (string) $definition['template_name'] : null,
            'template_language' => isset($definition['template_language']) ? (string) $definition['template_language'] : null,
            'template_variables' => array_values((array) ($definition['template_variables'] ?? [])),
            'daily_limit' => max(1, (int) ($definition['daily_limit'] ?? 1)),
            'retry_limit' => max(0, (int) ($definition['retry_limit'] ?? 1)),
            'requires_marketing_opt_in' => (bool) ($definition['requires_marketing_opt_in'] ?? false),
            'available' => $warning === null,
            'warning' => $warning,
        ];
    }

    private function resolveChannel(CustomerProfile $profile, string $configuredChannel): string
    {
        if ($configuredChannel !== 'preferred') {
            return $configuredChannel === 'email' ? 'email' : 'whatsapp';
        }

        $preferred = (string) ($profile->preferred_channel ?? 'whatsapp');

        if ($preferred === 'email' && filled($profile->email)) {
            return 'email';
        }

        if (filled($profile->phone)) {
            return 'whatsapp';
        }

        return filled($profile->email) ? 'email' : 'whatsapp';
    }

    private function availabilityWarning(CustomerProfile $profile, string $channel, bool $requiresMarketingOptIn): ?string
    {
        if ($channel === 'whatsapp') {
            if (! filled($profile->phone)) {
                return 'Falta telefono para enviar por WhatsApp.';
            }

            if (! $profile->whatsapp_opt_in) {
                return 'El cliente no tiene opt-in activo de WhatsApp.';
            }
        } else {
            if (! filled($profile->email)) {
                return 'Falta email para enviar esta plantilla.';
            }

            if (! $profile->email_opt_in) {
                return 'El cliente no tiene opt-in activo de email.';
            }
        }

        if ($requiresMarketingOptIn && ! $profile->marketing_opt_in) {
            return 'Esta plantilla necesita opt-in de marketing.';
        }

        return null;
    }

    private function defaultCtaUrl(CustomerProfile $profile, ?Tenant $tenant = null): ?string
    {
        $tenant ??= $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $businessType = is_object($tenant->business_type)
            ? (string) $tenant->business_type->value
            : (string) $tenant->business_type;

        if ($businessType === 'service') {
            return route('public.booking.index', ['tenant' => $tenant->slug]);
        }

        return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
    }
}
