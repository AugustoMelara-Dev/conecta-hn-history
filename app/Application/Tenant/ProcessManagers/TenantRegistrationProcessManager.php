<?php

declare(strict_types=1);

namespace App\Application\Tenant\ProcessManagers;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Identity\Events\TenantRegistered;
use Throwable;

final readonly class TenantRegistrationProcessManager
{
    private const STATE_REGISTERED = 'registered';

    private const STATE_ACKNOWLEDGED = 'acknowledged';

    private const STATE_FAILED = 'failed';

    private const STATE_COMPENSATED = 'compensated';

    private const TRANSITIONS = [
        self::STATE_REGISTERED => [self::STATE_ACKNOWLEDGED, self::STATE_FAILED],
        self::STATE_ACKNOWLEDGED => [],
        self::STATE_FAILED => [self::STATE_COMPENSATED],
        self::STATE_COMPENSATED => [],
    ];

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(TenantRegistered $event): void
    {
        $state = self::STATE_REGISTERED;

        try {
            // Note: Since the event currently only carries IDs, the Process Manager
            // needs a way to know the selected plan. In a fully decoupled Saga,
            // the TenantRegistered event should include the 'planType' or 'selectedPlan'.
            // For now, this is a placeholder structural decoupling. The handler will be
            // updated to pass the necessary data, or we retrieve the tenant here.

            $state = $this->transition($state, self::STATE_ACKNOWLEDGED);
            $this->logger->info('Process Manager: Acknowledged Tenant Registration', [
                'tenant_id' => $event->tenantId,
                'user_id' => $event->userId,
                'saga_state' => $state,
            ]);
        } catch (Throwable $e) {
            $state = $this->transition(self::STATE_FAILED, self::STATE_COMPENSATED);
            $this->logger->error('Process Manager: Failed to handle Tenant Registration', [
                'error' => $e->getMessage(),
                'tenant_id' => $event->tenantId,
                'saga_state' => $state,
            ]);
        }
    }

    private function transition(string $from, string $to): string
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \LogicException("Invalid tenant registration saga transition from {$from} to {$to}.");
        }

        return $to;
    }
}
