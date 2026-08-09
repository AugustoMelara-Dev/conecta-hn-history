<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Application\Ai\AssistantExecutor;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use App\Models\Contact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * ContactEnrichmentService - Enriquece datos de contacto via OpenAI structured-output.
 *
 * Costo AI: $0.02/enriquecimiento (gpt-4o-mini structured output).
 * Async via queue para no bloquear UI.
 */
class ContactEnrichmentService
{
    public function __construct(
        private CanonicalCrmEntityWriter $writer,
        private AssistantExecutor $assistants,
    ) {}

    /**
     * @return array{
     *     job_title?: string,
     *     company_name?: string,
     *     linkedin_url?: string,
     *     seniority?: string,
     *     industry?: string,
     *     confidence: float,
     *     raw: array<string, mixed>,
     * }
     */
    public function enrich(
        string $emailOrPhone,
        string $tenantId,
        string $actorId,
        ?string $correlationId = null,
    ): array {
        $context = new AssistantExecutionContext(
            tenantId: $tenantId,
            actorId: $actorId,
            correlationId: $correlationId ?? (string) Str::uuid(),
        );
        $result = $this->assistants->execute('contact_enrichment', ['identifier' => $emailOrPhone], $context);
        if (! $result->success || ! is_array($result->output)) {
            return ['confidence' => 0.0, 'status' => 'unavailable', 'raw' => []];
        }

        $fields = [];
        foreach ((array) ($result->output['fields'] ?? []) as $field) {
            if (is_array($field) && is_string($field['key'] ?? null)) {
                $fields[$field['key']] = is_string($field['value'] ?? null) ? $field['value'] : null;
            }
        }

        return array_merge($fields, [
            'confidence' => (float) ($result->output['confidence'] ?? 0),
            'status' => 'completed',
            'raw' => ['correlation_id' => $context->correlationId],
        ]);
    }

    /**
     * Aplica enrichment al contacto.
     */
    public function applyToContact(Contact $contact, string $tenantId, string $actorId): Contact
    {
        if (trim($tenantId) === '' || trim($actorId) === '') {
            throw new \InvalidArgumentException('CRM enrichment requires explicit tenant and actor identifiers.');
        }

        if (! hash_equals($tenantId, (string) $contact->tenant_id)) {
            throw new AuthorizationException('Contact does not belong to the requested tenant.');
        }

        $identifier = $contact->email ?? $contact->phone ?? '';

        if ($identifier === '') {
            return $contact;
        }

        $data = $this->enrich($identifier, $tenantId, $actorId);

        if (! array_key_exists('job_title', $data) || $data['job_title'] === null) {
            return $contact;
        }

        return $this->writer->updateContact(
            $tenantId,
            $actorId,
            (string) $contact->getKey(),
            ['job_title' => $data['job_title']],
        );
    }
}
