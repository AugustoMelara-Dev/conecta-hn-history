<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\OrderStateService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderStateServiceTest extends TestCase
{
    private OrderStateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderStateService(new NullEventDispatcher);
    }

    #[Test]
    public function it_allows_valid_transitions(): void
    {
        $this->assertTrue($this->service->canTransition(Order::STATUS_PENDING, Order::STATUS_CONFIRMED));
        $this->assertTrue($this->service->canTransition(Order::STATUS_READY, Order::STATUS_DELIVERED));
        $this->assertTrue($this->service->canTransition(Order::STATUS_PROCESSING, Order::STATUS_CANCELLED));
    }

    #[Test]
    public function it_rejects_invalid_transitions(): void
    {
        $this->assertFalse($this->service->canTransition(Order::STATUS_PENDING, Order::STATUS_READY));
        $this->assertFalse($this->service->canTransition(Order::STATUS_DELIVERED, Order::STATUS_CANCELLED));
    }

    #[Test]
    public function it_throws_for_invalid_transition_attempts(): void
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->expectExceptionMessage("No se puede pasar de 'pending' a 'ready'");

        $this->service->validateTransition(Order::STATUS_PENDING, Order::STATUS_READY);
    }

    #[Test]
    public function it_returns_allowed_transitions_for_each_state(): void
    {
        $this->assertSame(
            [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            $this->service->getAllowedTransitions(Order::STATUS_CONFIRMED)
        );

        $this->assertSame([], $this->service->getAllowedTransitions(Order::STATUS_DELIVERED));
        $this->assertSame([], $this->service->getAllowedTransitions('non-existent-status'));
    }
}

final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void {}
}
