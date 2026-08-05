<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\CreateOrderCommand;
use App\Application\Sales\Contracts\PublicOrderCreator;
use App\Application\Sales\DTOs\CreatePublicOrderData;
use App\Application\Sales\Results\PublicOrderCreationResult;

/**
 * Compatibility adapter from the public checkout DTO to the canonical order command.
 */
final readonly class EloquentPublicOrderCreator implements PublicOrderCreator
{
    public function __construct(private CanonicalOrderWriter $writer) {}

    public function create(CreatePublicOrderData $data): PublicOrderCreationResult
    {
        $result = $this->writer->create(new CreateOrderCommand(
            tenantId: $data->tenantId,
            actorId: $data->userId,
            idempotencyKey: $data->idempotencyKey,
            customerName: $data->customerName,
            customerPhone: $data->customerPhone,
            customerEmail: $data->customerEmail,
            customerAddress: $data->customerAddress,
            customerNotes: $data->customerNotes,
            deliveryType: $data->deliveryType,
            paymentMethod: $data->paymentMethod,
            items: $data->items,
            couponCode: $data->couponCode,
            userId: $data->userId,
            source: $data->source,
            publiclySellableOnly: true,
        ));

        return PublicOrderCreationResult::fromOrder($result->order, $result->replayed);
    }
}
