<?php

declare(strict_types=1);

namespace App\Infrastructure\EventVersioning;

/**
 * UpcasterChain - The Evolution Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LAZY TRANSFORMATION: Upgrades events only when read from the store.     ║
 * ║  CHAIN OF RESPONSIBILITY: Applies sequential upgrades (V1->V2->V3).      ║
 * ║  IMMUTABLE: Safe for concurrent access.                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpcasterChain
{
    /**
     * @param iterable<UpcasterInterface> $upcasters Registry of available upcasters
     */
    public function __construct(
        private iterable $upcasters
    ) {}

    /**
     * Upgrade a payload through all necessary versions for the target event.
     *
     * @param string $eventType
     * @param int $currentVersion The version stored in the event
     * @param array $payload
     * @return array The fully upgraded payload
     */
    public function upcast(string $eventType, int $currentVersion, array $payload): array
    {
        // Simple sequential loop.
        // In a complex system, we might sort upcasters by target version.
        // Here we rely on the registry order or simply finding the next step.
        // Since we don't have a sophisticated graph solver, we assume upcasters
        // are stateless and check 'supports'.
        
        // We act iteratively until no more upcasters verify 'supports'.
        // Warning: Infinite loop risk if upcaster doesn't satisfy 'supports' logic properly.
        // A safer approach:
        
        $workingPayload = $payload;
        $workingVersion = $currentVersion;
        
        $maxIterations = 50; // Safety brake
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $upcasterFound = false;

            foreach ($this->upcasters as $upcaster) {
                // Find an upcaster that takes us from $workingVersion -> Next
                if ($upcaster->supports($eventType, $workingVersion)) {
                    $workingPayload = $upcaster->upcast($workingPayload);
                    $workingVersion++; // Assume each upcaster increments version by 1
                    $upcasterFound = true;
                    // Restart loop to find next upcaster for new version
                    break;
                }
            }

            if (!$upcasterFound) {
                // No more upgrades available. We are done.
                break;
            }
            
            $iteration++;
        }

        return $workingPayload;
    }
}
