<?php

declare(strict_types=1);

namespace App\Domain\AIEnrichment\Data;

/**
 * AiPromptPayload - Immutable Value Object
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DATA TRANSFER OBJECT (DTO)                                              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Encapsulates the Prompt Artefact.                                       ║
 * ║  Designed for immutability and future extensibility (metadata/config).   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class BusinessProfilePromptRequest
{
    public function __construct(
        public string $content,
        public array $config = [] // Future-proofing: temperature, max_tokens, etc.
    ) {}
}
