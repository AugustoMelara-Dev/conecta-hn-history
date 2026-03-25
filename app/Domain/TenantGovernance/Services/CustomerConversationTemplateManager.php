<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerMessageTemplate;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class CustomerConversationTemplateManager
{
    public function __construct(
        private readonly CustomerAutomationManager $automationManager,
    ) {}

    /**
     * @return array{
     *     segment_key:string,
     *     journey:list<array<string, mixed>>,
     *     current_step:?array<string, mixed>,
     *     next_step:?array<string, mixed>,
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
        $journey = $this->journeyDefinition($profile, $segmentKey);
        $conversation = $this->resolveConversation($profile, $note);
        $currentStep = $this->currentStepFromConversation($conversation, $journey);
        $nextStep = $this->nextStepFromConversation($conversation);
        $recommendedTemplateKey = $this->recommendedTemplateKey($currentStep, $nextStep, $conversation);
        $templates = $this->templateKeysForConversation($profile, $segmentKey, $recommendedTemplateKey)
            ->map(fn (mixed $templateKey): array => $this->templateDefinition(
                $profile,
                is_string($templateKey) ? $templateKey : 'fast_follow_up',
                $note,
            ))
            ->values()
            ->all();

        return [
            'segment_key' => $segmentKey,
            'journey' => $journey,
            'current_step' => $currentStep,
            'next_step' => $nextStep,
            'templates' => $templates,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function journeyDefinition(CustomerProfile $profile, ?string $segmentKey = null): array
    {
        $segmentKey ??= (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $segmentDefinition = $this->segmentDefinition($profile, $segmentKey);

        return collect((array) ($segmentDefinition['journey'] ?? []))
            ->map(function (mixed $step, int $index): array {
                $definition = is_array($step) ? $step : [];

                return [
                    'key' => (string) ($definition['key'] ?? "step_{$index}"),
                    'title' => (string) ($definition['title'] ?? 'Paso del journey'),
                    'copy' => (string) ($definition['copy'] ?? 'Siguiente movimiento sugerido para esta conversacion.'),
                    'template_key' => isset($definition['template_key']) ? (string) $definition['template_key'] : null,
                    'lane' => (string) ($definition['lane'] ?? 'follow_up'),
                    'wait_hours' => max(0, (int) ($definition['wait_hours'] ?? 0)),
                    'auto_send' => (bool) ($definition['auto_send'] ?? $index > 0),
                ];
            })
            ->values()
            ->all();
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
            'provider_thread_key' => data_get($note?->metadata, 'provider_thread_key', $note?->conversation_key),
            'provider_reply_to' => data_get($note?->metadata, 'provider_message_id', data_get($note?->metadata, 'provider_reply_to')),
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
        $definition = $this->resolvedTemplateDefinition($profile, $templateKey);
        $channel = $this->resolveChannel($profile, (string) ($definition['channel'] ?? 'preferred'));
        $replace = [
            '{business_name}' => (string) ($tenant?->name ?? 'Conecta HN'),
            '{customer_name}' => (string) ($profile->name ?? 'cliente'),
            '{cta_url}' => (string) ($this->defaultCtaUrl($profile, $tenant) ?? ''),
        ];
        $warning = $this->availabilityWarning($profile, $channel, (bool) ($definition['requires_marketing_opt_in'] ?? false));

        if ((bool) ($definition['disabled'] ?? false)) {
            $warning = 'Esta plantilla esta desactivada para este negocio.';
        }

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

    /**
     * @return Collection<int, string>
     */
    private function templateKeysForConversation(CustomerProfile $profile, string $segmentKey, ?string $preferredKey = null): Collection
    {
        $segmentDefinition = $this->segmentDefinition($profile, $segmentKey);
        $configKeys = collect((array) ($segmentDefinition['templates'] ?? []))
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '');

        $customTemplates = $this->eligibleCustomTemplates($profile);
        $disabledKeys = $customTemplates
            ->where('is_active', false)
            ->pluck('template_key')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '');
        $customKeys = $customTemplates
            ->where('is_active', true)
            ->pluck('template_key')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '');

        $keys = $configKeys
            ->reject(fn (string $key): bool => $disabledKeys->contains($key))
            ->concat($customKeys)
            ->unique()
            ->values();

        if (! filled($preferredKey)) {
            return $keys;
        }

        return collect([(string) $preferredKey])
            ->concat($keys)
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->reject(fn (string $key): bool => $disabledKeys->contains($key))
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedTemplateDefinition(CustomerProfile $profile, string $templateKey): array
    {
        $definition = (array) config("customer-conversations.templates.{$templateKey}", []);
        $verticalDefinition = $this->verticalTemplateDefinition($profile, $templateKey);
        $customTemplate = $this->matchingCustomTemplate($profile, $templateKey);

        if ($verticalDefinition !== []) {
            $definition = array_replace($definition, $verticalDefinition);
        }

        if (! $customTemplate instanceof CustomerMessageTemplate) {
            return $definition;
        }

        return array_replace($definition, $this->customTemplateDefinition($customTemplate));
    }

    /**
     * @return Collection<int, CustomerMessageTemplate>
     */
    private function eligibleCustomTemplates(CustomerProfile $profile): Collection
    {
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $businessSegmentKey = $this->businessSegmentKey($profile);

        return CustomerMessageTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where(function ($query) use ($segmentKey): void {
                $query->whereNull('customer_segment')
                    ->orWhere('customer_segment', '')
                    ->orWhere('customer_segment', $segmentKey);
            })
            ->where(function ($query) use ($businessSegmentKey): void {
                $query->whereNull('business_segment_key')
                    ->orWhere('business_segment_key', '');

                if ($businessSegmentKey !== null) {
                    $query->orWhere('business_segment_key', $businessSegmentKey);
                }
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    private function matchingCustomTemplate(CustomerProfile $profile, string $templateKey): ?CustomerMessageTemplate
    {
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $businessSegmentKey = $this->businessSegmentKey($profile);

        return $this->eligibleCustomTemplates($profile)
            ->where('template_key', $templateKey)
            ->sortBy([
                fn (CustomerMessageTemplate $template): int => $template->customer_segment === $segmentKey ? 0 : 1,
                fn (CustomerMessageTemplate $template): int => $businessSegmentKey !== null && $template->business_segment_key === $businessSegmentKey ? 0 : 1,
                fn (CustomerMessageTemplate $template): int => (int) $template->priority,
                fn (CustomerMessageTemplate $template): string => (string) $template->name,
            ])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function customTemplateDefinition(CustomerMessageTemplate $template): array
    {
        $definition = [
            'label' => (string) $template->name,
            'requires_marketing_opt_in' => (bool) $template->requires_marketing_opt_in,
            'disabled' => ! $template->is_active,
        ];

        foreach ([
            'description',
            'channel',
            'automation_key',
            'subject',
            'body',
            'cta_label',
            'template_name',
            'template_language',
        ] as $key) {
            $value = $template->{$key};

            if (is_string($value) && $value !== '') {
                $definition[$key] = $value;
            }
        }

        if (is_array($template->template_variables) && $template->template_variables !== []) {
            $definition['template_variables'] = array_values($template->template_variables);
        }

        if ($template->metadata !== null) {
            foreach (['daily_limit', 'retry_limit', 'retry_backoff_minutes'] as $governanceKey) {
                $value = data_get($template->metadata, $governanceKey);

                if ($value !== null) {
                    $definition[$governanceKey] = (int) $value;
                }
            }
        }

        return $definition;
    }

    private function businessSegmentKey(CustomerProfile $profile): ?string
    {
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return filled($tenant->business_segment_key) ? (string) $tenant->business_segment_key : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function segmentDefinition(CustomerProfile $profile, string $segmentKey): array
    {
        $base = (array) config("customer-conversations.segments.{$segmentKey}", []);
        $vertical = (array) config('customer-conversations.verticals.'.$this->businessSegmentKey($profile).".segments.{$segmentKey}", []);

        return array_replace_recursive($base, $vertical);
    }

    /**
     * @return array<string, mixed>
     */
    private function verticalTemplateDefinition(CustomerProfile $profile, string $templateKey): array
    {
        $businessSegmentKey = $this->businessSegmentKey($profile);

        if ($businessSegmentKey === null) {
            return [];
        }

        return (array) config("customer-conversations.verticals.{$businessSegmentKey}.templates.{$templateKey}", []);
    }

    private function resolveConversation(CustomerProfile $profile, ?CustomerNote $note = null): ?CustomerConversation
    {
        $conversation = $note?->conversation()
            ->withoutGlobalScopes()
            ->with('journeyRun')
            ->first();

        if ($conversation instanceof CustomerConversation) {
            return $conversation;
        }

        return CustomerConversation::query()
            ->withoutGlobalScopes()
            ->with('journeyRun')
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where('customer_profile_id', (int) $profile->getKey())
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->latest('updated_at')
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $journey
     * @return array<string, mixed>|null
     */
    private function currentStepFromConversation(?CustomerConversation $conversation, array $journey): ?array
    {
        if ($conversation?->journeyRun !== null) {
            $currentStep = data_get($conversation->journeyRun->metadata, 'current_step');

            if (is_array($currentStep)) {
                return $currentStep;
            }

            $currentStepKey = (string) ($conversation->journeyRun->current_step_key ?? '');

            if ($currentStepKey !== '') {
                return collect($journey)
                    ->first(fn (array $step): bool => (string) ($step['key'] ?? '') === $currentStepKey);
            }
        }

        return $journey[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextStepFromConversation(?CustomerConversation $conversation): ?array
    {
        $nextStep = data_get($conversation?->journeyRun?->metadata, 'next_step');

        return is_array($nextStep) ? $nextStep : null;
    }

    /**
     * @param  array<string, mixed>|null  $currentStep
     * @param  array<string, mixed>|null  $nextStep
     */
    private function recommendedTemplateKey(?array $currentStep, ?array $nextStep, ?CustomerConversation $conversation): ?string
    {
        $templateKey = $conversation?->journeyRun?->next_template_key;

        if (filled($templateKey)) {
            return (string) $templateKey;
        }

        $currentTemplate = $currentStep['template_key'] ?? null;

        if (is_string($currentTemplate) && $currentTemplate !== '') {
            return $currentTemplate;
        }

        $nextTemplate = $nextStep['template_key'] ?? null;

        return is_string($nextTemplate) && $nextTemplate !== ''
            ? $nextTemplate
            : null;
    }
}
