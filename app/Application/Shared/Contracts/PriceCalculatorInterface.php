<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Illuminate\Support\Collection;

interface PriceCalculatorInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  Collection<int, \App\Models\Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public function calculate(array $cartItems, Collection $products): array;
}
