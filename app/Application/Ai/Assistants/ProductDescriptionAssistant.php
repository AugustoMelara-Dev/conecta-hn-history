<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class ProductDescriptionAssistant extends BaseAssistant
{
    public function key(): string { return 'product_description'; }
    public function name(): string { return 'Product Description'; }
    public function description(): string { return 'Genera descripci�n de producto.'; }
    public function estimateCost(): float { return 0.03; }
    public function category(): string { return 'Cat�logo'; }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['product_name'])) {
            return AssistantResult::failure('Missing product_name');
        }
        return $this->mockOutput($input, [
            'description' => "{$input['product_name']} - Descripci�n generada con AI.",
            'tags' => ['calidad', 'premium'],
            'seo_keywords' => ['producto', 'premium', 'calidad'],
        ]);
    }
}
