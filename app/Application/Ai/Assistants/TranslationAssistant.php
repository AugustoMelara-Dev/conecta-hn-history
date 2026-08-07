<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class TranslationAssistant extends BaseAssistant
{
    public function key(): string { return 'translation'; }
    public function name(): string { return 'Translation'; }
    public function description(): string { return 'Traduce texto a idioma destino.'; }
    public function estimateCost(): float { return 0.02; }
    public function category(): string { return 'Communications'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['text']) || ! isset($input['target_lang'])) {
            return AssistantResult::failure('Missing text or target_lang');
        }
        return $this->mockOutput($input, [
            'translation' => '[Traducción al ' . $input['target_lang'] . ']',
            'source_lang' => 'es',
            'target_lang' => $input['target_lang'],
        ]);
    }
}
