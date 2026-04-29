<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\CustomerMessageTemplate;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class CustomerConversationTemplateCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function journeyDefinition(CustomerProfile $profile, ?string $segmentKey = null): array
    {
        $resolvedSegmentKey = $this->resolveManifestSegmentKey(
            $profile,
            $segmentKey ?? (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new'),
        );
        $segmentDefinition = $this->segmentDefinition($profile, $resolvedSegmentKey);

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
                    'branches' => array_values(array_filter((array) ($definition['branches'] ?? []), 'is_array')),
                ];
            })
            ->values()
            ->all();
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
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;
        $definition = $this->resolvedTemplateDefinition($profile, $templateKey);
        $channel = $this->resolveChannel($profile, (string) ($definition['channel'] ?? 'preferred'));
        $replace = [
            '{business_name}' => (string) ($tenant->name ?? 'Vito Business OS'),
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

    /**
     * @return Collection<int, string>
     */
    public function templateKeysForConversation(CustomerProfile $profile, string $segmentKey, ?string $preferredKey = null): Collection
    {
        $resolvedSegmentKey = $this->resolveManifestSegmentKey($profile, $segmentKey);
        $segmentDefinition = $this->segmentDefinition($profile, $resolvedSegmentKey);
        $configKeys = collect((array) ($segmentDefinition['templates'] ?? []))
            ->map(static fn (string $key): string => $key);
        $manifestKeys = $this->manifestTemplateKeys($profile);

        if ($manifestKeys->isNotEmpty()) {
            $configKeys = $configKeys
                ->intersect($manifestKeys)
                ->concat($manifestKeys)
                ->unique()
                ->values();
        }

        $customTemplates = $this->eligibleCustomTemplates($profile);
        $disabledKeys = $customTemplates
            ->where('is_active', false)
            ->pluck('template_key')
            ->map(static fn (string $key): string => $key);
        $customKeys = $customTemplates
            ->where('is_active', true)
            ->pluck('template_key')
            ->map(static fn (string $key): string => $key);

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
            ->map(static fn (string $key): string => $key)
            ->reject(fn (string $key): bool => $disabledKeys->contains($key))
            ->unique()
            ->values();
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

        $businessType = (string) $tenant->business_type->value;

        if ($businessType === 'service') {
            return route('public.booking.index', ['tenant' => $tenant->slug]);
        }

        return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
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
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;

        if (! $tenant instanceof Tenant) {
            return null;
        }

        if (filled($tenant->rubro_key)) {
            return (string) $tenant->rubro_key;
        }

        return filled($tenant->business_segment_key) ? (string) $tenant->business_segment_key : null;
    }

    /**
     * @return Collection<int, string>
     */
    private function manifestTemplateKeys(CustomerProfile $profile): Collection
    {
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;

        if (! $tenant instanceof Tenant) {
            return collect();
        }

        return collect((array) data_get(app(RubroManifestReaderInterface::class)->crmForTenant($tenant), 'templates', []))
            ->map(static fn (mixed $key): string => (string) $key)
            ->filter(static fn (string $key): bool => $key !== '')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function segmentDefinition(CustomerProfile $profile, string $segmentKey): array
    {
        $base = (array) config("customer-conversations.segments.{$segmentKey}", []);
        $vertical = (array) config('customer-conversations.verticals.'.$this->businessSegmentKey($profile).".segments.{$segmentKey}", []);
        $manifest = (array) data_get($this->manifestCrm($profile), "journeys.{$segmentKey}", []);

        if ($manifest !== []) {
            return array_replace_recursive([
                'journey' => [],
                'templates' => [],
            ], $manifest);
        }

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

        $legacy = (array) config("customer-conversations.verticals.{$businessSegmentKey}.templates.{$templateKey}", []);
        $manifest = (array) data_get($this->manifestCrm($profile), "template_overrides.{$templateKey}", []);

        return array_replace_recursive($legacy, $manifest);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestCrm(CustomerProfile $profile): array
    {
        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(RubroManifestReaderInterface::class)->crmForTenant($tenant);
    }

    private function resolveManifestSegmentKey(CustomerProfile $profile, string $segmentKey): string
    {
        $normalized = trim($segmentKey);
        if ($normalized === '') {
            $normalized = 'new';
        }

        $manifestCrm = $this->manifestCrm($profile);
        $journeys = (array) ($manifestCrm['journeys'] ?? []);

        if (array_key_exists($normalized, $journeys)) {
            return $normalized;
        }

        $aliases = [
            'new' => 'prospect',
            'lead' => 'prospect',
            'active' => 'repeat',
            'vip' => 'repeat',
            'booked' => 'booked_service',
            'booked_service' => 'booked_service',
        ];
        $alias = $aliases[$normalized] ?? null;

        if (is_string($alias) && $alias !== '' && array_key_exists($alias, $journeys)) {
            return $alias;
        }

        $preferred = array_values(array_filter(
            (array) ($manifestCrm['preferred_segments'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));

        foreach ($preferred as $candidate) {
            if (array_key_exists($candidate, $journeys)) {
                return $candidate;
            }
        }

        if ($journeys !== []) {
            $firstJourneyKey = array_key_first($journeys);
            if (is_string($firstJourneyKey) && $firstJourneyKey !== '') {
                return $firstJourneyKey;
            }
        }

        return $normalized;
    }
}
