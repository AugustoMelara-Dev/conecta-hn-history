<?php

declare(strict_types=1);

namespace App\Domain\AIEnrichment\Prompts;

use App\Domain\AIEnrichment\Data\AiPromptPayload;

/**
 * BusinessProfilePromptBuilder - Business Logic for AI Context
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PROMPT ENGINEERING DOMAIN SERVICE                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Encapsulates the complexity of "Prompt Engineering" logic.             ║
 * ║  Ensures prompts are structured, consistent, and versionable.            ║
 * ║  Uses HEREDOC for maximum readability and zero-concat pollution.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BusinessProfilePromptBuilder
{
    /**
     * Build the structured prompt for the AI.
     *
     * @param string $businessName
     * @param string $activity
     * @param string $location
     * @param string|null $extraDetails
     * @return AiPromptPayload Validated prompt object ready for LLM ingestion
     */
    public static function build(
        string $businessName,
        string $activity,
        string $location,
        ?string $extraDetails = null
    ): AiPromptPayload {
        $context = $extraDetails 
            ? "Contexto Adicional Proporcionado: \"{$extraDetails}\""
            : "Contexto Adicional: Ninguno.";

        $promptContent = <<<PROMPT
Role: Expert Business Consultant & Copywriter.
Task: Generate a high-converting, professional business profile description.

Input Data:
- Business Name: "{$businessName}"
- Core Activity: "{$activity}"
- Location: "{$location}"
- {$context}

Output Requirements:
1. Tone: Professional, welcoming, and persuasive.
2. Structure: 
   - Headline: Catchy and relevant.
   - Body: 2-3 paragraphs describing the value proposition.
   - Call to Action: Subtle invitation to visit.
3. Language: Spanish (Neutral/Latam).
4. Formatting: Plain text (no markdown), ready for database storage.

Do not include "Here is the profile" or meta-commentary. Just output the content.
PROMPT;

        return new AiPromptPayload(
            content: $promptContent,
            config: ['temperature' => 0.7] // Default creativity configuration
        );
    }
}
