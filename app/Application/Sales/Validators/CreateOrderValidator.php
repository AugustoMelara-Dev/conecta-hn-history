<?php

declare(strict_types=1);

namespace App\Application\Validators\Sales;

use App\Application\Sales\Commands\CreateOrderCommand;

/**
 * CreateOrderValidator - Structural Validation Rules
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRUCTURAL ONLY: Validates message format, NOT business rules.         ║
 * ║  DOMAIN RULES: Stock availability checked in InventoryManager.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CreateOrderValidator
{
    /**
     * Get the validation rules that apply to the command.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(CreateOrderCommand $command): array
    {
        return [
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
            'customerId' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'couponCode' => ['nullable', 'string', 'max:50'],
        ];
    }
}
