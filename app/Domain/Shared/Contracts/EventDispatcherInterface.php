<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * EventDispatcherInterface - Framework Agnostic Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PORT: Decouples Application Layer from Laravel's Event Dispatcher.     ║
 * ║  METHOD: dispatch(object $event): void                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
