<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class TaxCategoryAssistant extends BaseAssistant
{
    public function key(): string { return 'tax_category'; }
    public function name(): string { return 'Tax Category Classifier'; }
    public function description(): string { return 'Clasifica producto en categoría fiscal.'; }
    public function estimateCost(): float { return 0.02; }
    public function category(): string { return 'Catálogo'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['product_id'])) {
            return AssistantResult::failure('Missing product_id');
        }
        return $this->mockOutput($input, [
            'tax_category' => 'standard',
            'tax_rate' => 0.15,
            'confidence' => 0.88,
        ]);
    }
}
