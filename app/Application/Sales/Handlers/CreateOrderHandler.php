<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\CreateOrderCommand as CanonicalCreateOrderCommand;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Exceptions\UserNotFoundException;
use Illuminate\Support\Str;

/**
 * Compatibility adapter for callers still dispatching the legacy sales command.
 */
final readonly class CreateOrderHandler implements CreateOrderHandlerInterface
{
    public function __construct(
        private CanonicalOrderWriter $writer,
        private UserRepositoryInterface $users,
    ) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        $user = $this->users->findById((string) $command->customerId, $command->tenantId);
        if ($user === null) {
            throw new UserNotFoundException("User {$command->customerId} not found");
        }

        $userId = $user->id();
        $requestorId = $command->requestorId === null ? null : (string) $command->requestorId;
        $result = $this->writer->create(new CanonicalCreateOrderCommand(
            tenantId: $command->tenantId,
            actorId: Str::isUuid($requestorId) ? $requestorId : null,
            idempotencyKey: $command->getIdempotencyKey(),
            customerName: $command->customerName ?? $user->name(),
            customerPhone: (string) $command->customerPhone,
            customerEmail: $command->customerEmail ?? $user->email(),
            customerAddress: $command->customerAddress,
            customerNotes: $command->customerNotes,
            deliveryType: $command->deliveryType,
            paymentMethod: $command->paymentMethod,
            items: $command->items,
            couponCode: $command->couponCode,
            userId: Str::isUuid($userId) ? $userId : null,
            source: 'application_command',
            requestedCurrency: $command->currency,
        ));

        return CreateOrderResult::fromEntity($result->order);
    }
}
