<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Projections;

use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Infrastructure\Common\EventStore\ProjectorInterface;
use App\Infrastructure\Identity\Projections\UserProjector;

/**
 * ProjectionManager - The Time Machine Operator
 *
 * Orchestrates the "Reset & Replay" process for all registered projectors.
 * Handles bit-rot degradation and memory-efficient streaming.
 */
final class ProjectionManager
{
    /** @var array<string, ProjectorInterface> */
    private array $projectors = [];

    public function __construct(
        private EventStoreRepositoryInterface $eventStore,
        // INJECTION: Register Projectors Here
        UserProjector $userProjector
    ) {
        $this->register($userProjector);
    }

    private function register(object $projector): void
    {
        $this->projectors[get_class($projector)] = $projector;
    }

    /**
     * Replay ALL registered projectors.
     */
    public function replayAll(?callable $onProgress = null): void
    {
        $this->resetAll();
        $this->streamAndProject($this->projectors, $onProgress);
    }

    /**
     * Replay a SPECIFIC projector by class name.
     */
    public function replayProjector(string $projectorClassName, ?callable $onProgress = null): void
    {
        if (! isset($this->projectors[$projectorClassName])) {
            throw new \InvalidArgumentException("Projector [{$projectorClassName}] not found.");
        }

        $projector = $this->projectors[$projectorClassName];

        // 1. Reset specific projector
        $projector->reset();

        // 2. Stream and project only to this one
        $this->streamAndProject([$projectorClassName => $projector], $onProgress);
    }

    private function resetAll(): void
    {
        foreach ($this->projectors as $projector) {
            $projector->reset();
        }
    }

    /**
     * The Heavy Lifter.
     */
    private function streamAndProject(array $targetProjectors, ?callable $onProgress = null): void
    {
        $count = 0;

        // MEMORY CONSTANT O(1) Stream
        $stream = $this->eventStore->streamAll();

        foreach ($stream as $event) {

            // Dispatch to interested projectors
            foreach ($targetProjectors as $projector) {
                if ($projector->supports($event)) {
                    $projector->project($event);
                }
            }

            $count++;
            if ($onProgress && $count % 100 === 0) {
                $onProgress($count);
            }
        }

        // Final progress update
        if ($onProgress) {
            $onProgress($count);
        }
    }
}
