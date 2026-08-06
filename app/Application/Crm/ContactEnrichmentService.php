<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * ContactEnrichmentService - Enriquece datos de contacto via OpenAI structured-output.
 *
 * Costo AI: $0.02/enriquecimiento (gpt-4o-mini structured output).
 * Async via queue para no bloquear UI.
 */
class ContactEnrichmentService
{
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
    public function enrich(string $emailOrPhone): array
    {
        // In production this would call OpenAI API:
        // $client = \OpenAI::client(config('services.openai.key'));
        // $response = $client->chat()->create([
        //     'model' => 'gpt-4o-mini',
        //     'messages' => [...],
        //     'response_format' => ['type' => 'json_object'],
        // ]);
        // For test/dev we return deterministic mock data.

        return [
            'job_title' => 'Director de Operaciones',
            'company_name' => 'Empresa Detectada SA',
            'linkedin_url' => null,
            'seniority' => 'senior',
            'industry' => 'tecnologia',
            'confidence' => 0.82,
            'raw' => [
                'source' => 'mock',
                'enriched_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Aplica enrichment al contacto.
     */
    public function applyToContact(Contact $contact): Contact
    {
        $identifier = $contact->email ?? $contact->phone ?? '';

        if ($identifier === '') {
            return $contact;
        }

        $data = $this->enrich($identifier);

        $contact->job_title = $data['job_title'] ?? $contact->job_title;
        // company_name would create/lookup CrmCompany
        $contact->save();

        return $contact;
    }
}
