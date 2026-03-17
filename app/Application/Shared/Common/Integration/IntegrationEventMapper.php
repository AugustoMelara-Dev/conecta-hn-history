<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Integration;

use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Domain\Identity\Events\UserRegistered;
use App\Domain\TenantGovernance\Events\TenantCreated;
use App\Domain\Shared\Contracts\ClockInterface;

/**
 * IntegrationEventMapper - The Diplomat
 *
 * Transforms internal Domain Events into stable, versioned Public Events.
 * Filters sensitive data and decouples internal structure from external contract.
 */
final readonly class IntegrationEventMapper
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {}

    public function map(array $domainEvents): array
    {
        $outboxMessages = [];

        foreach ($domainEvents as $event) {
            $message = match (true) {
                $event instanceof UserRegistered => $this->mapUserRegistered($event),
                $event instanceof TenantCreated => $this->mapTenantCreated($event),
                default => null,
            };

            if ($message) {
                $outboxMessages[] = $message;
            }
        }

        return $outboxMessages;
    }

    private function mapUserRegistered(UserRegistered $event): array
    {
        return [
            'id' => $this->idGenerator->generate(),
            'type' => 'Identity.UserRegistered.v1',
            'payload' => json_encode([
                'user_id' => $event->user_id,
                'tenant_id' => $event->tenant_id,
                'email' => $event->email,
                'role' => $event->role,
                'registered_at' => $event->occurredOn()->format(\DateTimeInterface::ATOM),
            ]),
            'occurred_at' => $this->clock->now(),
            'published_at' => null,
            'status' => 'PENDING',
        ];
    }

    private function mapTenantCreated(TenantCreated $event): array
    {
        return [
            'id' => $this->idGenerator->generate(),
            'type' => 'Identity.TenantCreated.v1',
            'payload' => json_encode([
                'tenant_id' => $event->id,
                'name' => $event->name,
                'domain' => $event->domain,
                'created_at' => $event->occurredOn()->format(\DateTimeInterface::ATOM),
            ]),
            'occurred_at' => $this->clock->now(),
            'published_at' => null,
            'status' => 'PENDING',
        ];
    }
}
