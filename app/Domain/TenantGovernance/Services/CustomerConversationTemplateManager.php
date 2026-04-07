<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class CustomerConversationTemplateManager
{
    public function __construct(
        private readonly CustomerAutomationManager $automationManager,
        private readonly CustomerJourneyBranchResolver $branchResolver,
        private readonly CustomerConversationTemplateCatalog $templateCatalog,
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

        if ($conversation === null) {
            $journeyKey = $this->journeyKey($profile, $segmentKey);
            $currentStep = $this->branchResolver->resolve($profile, null, $currentStep, $journeyKey, 0);
            $nextStep = $this->branchResolver->resolve($profile, null, $nextStep, $journeyKey, 1);
        }

        $recommendedTemplateKey = $this->recommendedTemplateKey($currentStep, $nextStep, $conversation);
        $templates = $this->templateKeysForConversation($profile, $segmentKey, $recommendedTemplateKey)
            ->map(fn (mixed $templateKey): array => $this->templateDefinition(
                $profile,
                (string) $templateKey,
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

        return $this->templateCatalog->journeyDefinition($profile, $segmentKey);
    }

    public function send(CustomerProfile $profile, string $templateKey, ?CustomerNote $note = null, ?string $actorUserId = null): CustomerTouchpoint
    {
        $definition = $this->templateDefinition($profile, $templateKey, $note);
        $conversation = $this->resolveConversation($profile, $note);
        $journeyContext = $this->journeyContext($profile, $conversation, $templateKey);

        if (! $definition['available']) {
            throw new \RuntimeException((string) ($definition['warning'] ?? 'La plantilla no se puede enviar en este momento.'));
        }

        $channel = $definition['channel'];
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;

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
            'journey_key' => $journeyContext['journey_key'],
            'journey_lane' => $journeyContext['lane'],
            'journey_step_key' => $journeyContext['step_key'],
            'journey_step_title' => $journeyContext['step_title'],
            'journey_branch_key' => $journeyContext['branch_key'],
            'journey_branch_label' => $journeyContext['branch_label'],
            'journey_sequence_key' => $journeyContext['sequence_key'],
            'journey_sequence_label' => $journeyContext['sequence_label'],
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
        return $this->templateCatalog->templateDefinition($profile, $templateKey, $note);
    }

    /**
     * @return Collection<int, string>
     */
    private function templateKeysForConversation(CustomerProfile $profile, string $segmentKey, ?string $preferredKey = null): Collection
    {
        return $this->templateCatalog->templateKeysForConversation($profile, $segmentKey, $preferredKey);
    }

    private function resolveConversation(CustomerProfile $profile, ?CustomerNote $note = null): ?CustomerConversation
    {
        $conversation = $note?->conversation()
            ->withoutGlobalScopes()
            ->with([
                'journeyRun' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->first();

        if ($conversation instanceof CustomerConversation) {
            return $conversation;
        }

        return CustomerConversation::query()
            ->withoutGlobalScopes()
            ->with([
                'journeyRun' => fn ($query) => $query->withoutGlobalScopes(),
            ])
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
        $journeyRun = $conversation?->journeyRun;

        if ($journeyRun instanceof CustomerJourneyRun) {
            $currentStep = data_get($journeyRun->metadata, 'current_step');

            if (is_array($currentStep)) {
                return $currentStep;
            }

            $currentStepKey = (string) ($journeyRun->current_step_key ?? '');

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
        $journeyRun = $conversation?->journeyRun;
        $nextStep = $journeyRun instanceof CustomerJourneyRun ? data_get($journeyRun->metadata, 'next_step') : null;

        return is_array($nextStep) ? $nextStep : null;
    }

    /**
     * @param  array<string, mixed>|null  $currentStep
     * @param  array<string, mixed>|null  $nextStep
     */
    private function recommendedTemplateKey(?array $currentStep, ?array $nextStep, ?CustomerConversation $conversation): ?string
    {
        $journeyRun = $conversation?->journeyRun;
        $templateKey = $journeyRun instanceof CustomerJourneyRun ? $journeyRun->next_template_key : null;

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

    /**
     * @return array{
     *     journey_key:?string,
     *     lane:?string,
     *     step_key:?string,
     *     step_title:?string,
     *     branch_key:?string,
     *     branch_label:?string,
     *     sequence_key:?string,
     *     sequence_label:?string
     * }
     */
    private function journeyContext(CustomerProfile $profile, ?CustomerConversation $conversation, string $templateKey): array
    {
        $journeyRunRecord = $conversation?->journeyRun()
            ->withoutGlobalScopes()
            ->first();
        $journeyRun = $journeyRunRecord instanceof CustomerJourneyRun ? $journeyRunRecord : null;
        $currentStep = is_array(data_get($journeyRun?->metadata, 'current_step'))
            ? data_get($journeyRun?->metadata, 'current_step')
            : null;
        $nextStep = is_array(data_get($journeyRun?->metadata, 'next_step'))
            ? data_get($journeyRun?->metadata, 'next_step')
            : null;
        $step = $this->matchingJourneyStep($templateKey, $currentStep, $nextStep);

        if ($step === null) {
            $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
            $journeyKey = $this->journeyKey($profile, $segmentKey);
            $steps = $this->journeyDefinition($profile, $segmentKey);
            $step = $this->matchingJourneyStep(
                $templateKey,
                $this->branchResolver->resolve($profile, $conversation, $steps[0] ?? null, $journeyKey, 0),
                $this->branchResolver->resolve($profile, $conversation, $steps[1] ?? null, $journeyKey, 1),
            );
        }

        $journeyKey = $journeyRun instanceof CustomerJourneyRun && filled($journeyRun->journey_key)
            ? (string) $journeyRun->journey_key
            : $this->journeyKey($profile, (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new'));

        if (is_array($step)) {
            $step = $this->hydrateJourneyStepContext($step, $journeyKey);
        }

        return [
            'journey_key' => $journeyKey,
            'lane' => is_array($step) ? (string) ($step['lane'] ?? '') : null,
            'step_key' => is_array($step) ? (string) ($step['key'] ?? '') : null,
            'step_title' => is_array($step) ? (string) ($step['title'] ?? '') : null,
            'branch_key' => is_array($step) ? (string) ($step['branch_key'] ?? 'base') : null,
            'branch_label' => is_array($step) ? (string) ($step['branch_label'] ?? 'Ruta base') : null,
            'sequence_key' => is_array($step) ? (string) ($step['sequence_key'] ?? '') : null,
            'sequence_label' => is_array($step) ? (string) ($step['sequence_label'] ?? '') : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $currentStep
     * @param  array<string, mixed>|null  $nextStep
     * @return array<string, mixed>|null
     */
    private function matchingJourneyStep(string $templateKey, ?array $currentStep, ?array $nextStep): ?array
    {
        foreach ([$currentStep, $nextStep] as $step) {
            if (is_array($step) && (string) ($step['template_key'] ?? '') === $templateKey) {
                return $step;
            }
        }

        return $currentStep;
    }

    private function journeyKey(CustomerProfile $profile, string $segmentKey): string
    {
        $businessSegmentKey = $this->businessSegmentKey($profile);

        return $businessSegmentKey !== null
            ? "{$businessSegmentKey}:{$segmentKey}"
            : $segmentKey;
    }

    private function businessSegmentKey(CustomerProfile $profile): ?string
    {
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return filled($tenant->business_segment_key) ? (string) $tenant->business_segment_key : null;
    }

    private function defaultCtaUrl(CustomerProfile $profile, ?Tenant $tenant = null): ?string
    {
        $tenant ??= $profile->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $businessType = (string) $tenant->business_type->value;

        if ($businessType === 'service') {
            return route('public.booking.index', ['tenant' => $tenant->slug]);
        }

        return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function hydrateJourneyStepContext(array $step, string $journeyKey): array
    {
        $branchKey = (string) ($step['branch_key'] ?? 'base');
        $stepKey = (string) ($step['key'] ?? 'step');

        $step['branch_key'] = $branchKey;
        $step['branch_label'] = (string) ($step['branch_label'] ?? 'Ruta base');
        $step['sequence_key'] = (string) ($step['sequence_key'] ?? "{$journeyKey}:{$stepKey}:{$branchKey}");
        $step['sequence_label'] = (string) ($step['sequence_label'] ?? ($step['title'] ?? 'Secuencia CRM'));

        return $step;
    }
}
