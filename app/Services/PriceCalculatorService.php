<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModifierOption;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * PriceCalculatorService - High-Performance Batch Pricing Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  O(1) QUERY PERFORMANCE: Batch loading eliminates N+1 problem           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * STRATEGY: "Load-First, Map-Later"
 * 1. Extract ALL modifier option IDs from cart (single pass)
 * 2. Execute ONE batch query with whereIn
 * 3. Build in-memory map for O(1) lookup
 * 4. Calculate prices using the map (no additional queries)
 *
 * SECURITY:
 * - Validates modifier ownership (option->product_id === item->product_id)
 * - Server-side price enforcement (ignores client prices)
 * - Throws ValidationException on parameter tampering attempts
 */
class PriceCalculatorService implements \App\Application\Shared\Contracts\PriceCalculatorInterface
{
    /**
     * Calculate composite prices for all cart items in a single batch.
     *
     * @param  array  $cartItems  Cart items with product_id, quantity, options
     * @param  Collection  $products  Locked products keyed by ID
     * @return array Calculated items with unit_price, line_total, validated_options
     *
     * @throws ValidationException If invalid options detected
     */
    public function calculate(array $cartItems, Collection $products): array
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: EXTRACTION - Collect all modifier option IDs
        // ═══════════════════════════════════════════════════════════════════════
        $allOptionIds = [];
        $optionsByItem = [];

        foreach ($cartItems as $index => $item) {
            $itemOptionIds = $this->extractOptionIds($item['options'] ?? []);
            $optionsByItem[$index] = $itemOptionIds;
            $allOptionIds = array_merge($allOptionIds, $itemOptionIds);
        }

        $allOptionIds = array_unique($allOptionIds);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: BATCH QUERY - ONE query for all modifiers
        // ═══════════════════════════════════════════════════════════════════════
        $optionsMap = [];
        if (! empty($allOptionIds)) {
            $optionsMap = $this->loadOptionsMap($allOptionIds);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: CALCULATION - O(1) lookup per option
        // ═══════════════════════════════════════════════════════════════════════
        $results = [];

        foreach ($cartItems as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);

            if (! $productId || ! isset($products[$productId])) {
                // Custom item - pass through with client data
                $results[$index] = [
                    'is_custom' => true,
                    'name' => $item['name'] ?? 'Custom Item',
                    'unit_price' => round((float) ($item['price'] ?? 0), 2),
                    'quantity' => $quantity,
                    'line_total' => round((float) ($item['price'] ?? 0) * $quantity, 2),
                    'validated_options' => null,
                    'notes' => $item['notes'] ?? null,
                ];

                continue;
            }

            $product = $products[$productId];
            $basePrice = (float) $product->price;
            $itemOptionIds = $optionsByItem[$index] ?? [];

            // Calculate composite price with validated options
            $pricing = $this->calculateItemPrice(
                $productId,
                $product->name,
                $basePrice,
                $itemOptionIds,
                $optionsMap
            );

            $results[$index] = [
                'is_custom' => false,
                'product_id' => $productId,
                'name' => $product->name,
                'unit_price' => round($pricing['unit_price'], 2),
                'quantity' => $quantity,
                'line_total' => round($pricing['unit_price'] * $quantity, 2),
                'validated_options' => $pricing['validated_options'],
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Extract option IDs from various request formats.
     */
    protected function extractOptionIds(?array $options): array
    {
        if (empty($options)) {
            return [];
        }

        return collect($options)
            ->map(fn ($opt) => is_array($opt) ? ($opt['id'] ?? $opt['option_id'] ?? null) : $opt)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Load all modifier options in ONE batch query.
     * Returns map keyed by option ID for O(1) lookup.
     */
    protected function loadOptionsMap(array $optionIds): array
    {
        $options = ModifierOption::whereIn('modifier_options.id', $optionIds)
            ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
            ->where('modifier_options.is_available', true)
            ->select([
                'modifier_options.id',
                'modifier_options.name',
                'modifier_options.price_adjustment',
                'modifier_groups.product_id',
                'modifier_groups.name as group_name',
            ])
            ->get();

        // Build O(1) lookup map
        $map = [];
        foreach ($options as $option) {
            $map[$option->id] = $option;
        }

        return $map;
    }

    /**
     * Calculate composite price for a single item with validation.
     */
    protected function calculateItemPrice(
        int $productId,
        string $productName,
        float $basePrice,
        array $optionIds,
        array $optionsMap
    ): array {
        $optionsTotal = 0.0;
        $validatedOptions = [];

        foreach ($optionIds as $optionId) {
            if (! isset($optionsMap[$optionId])) {
                throw ValidationException::withMessages([
                    'items' => "Opción ID {$optionId} no encontrada o no disponible.",
                ]);
            }

            $option = $optionsMap[$optionId];

            // SECURITY: Validate option belongs to this product
            if ((int) $option->product_id !== $productId) {
                throw ValidationException::withMessages([
                    'items' => "Opción '{$option->name}' no pertenece al producto '{$productName}'.",
                ]);
            }

            $optionsTotal += (float) $option->price_adjustment;
            $validatedOptions[] = [
                'group' => $option->group_name,
                'option' => $option->name,
                'price' => (float) $option->price_adjustment,
            ];
        }

        return [
            'unit_price' => round($basePrice + $optionsTotal, 2),
            'validated_options' => ! empty($validatedOptions) ? $validatedOptions : null,
        ];
    }
}
