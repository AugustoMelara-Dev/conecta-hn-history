<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

final readonly class AiRegistryBackfillReport
{
    public function __construct(
        public int $providers,
        public int $providerVersions,
        public int $models,
        public int $modelVersions,
        public int $agentDefinitions,
        public int $agentVersions,
        public int $promptDefinitions,
        public int $promptVersions,
        public int $activations,
        public int $gaps,
        public int $conflicts,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'providers' => $this->providers,
            'providerVersions' => $this->providerVersions,
            'models' => $this->models,
            'modelVersions' => $this->modelVersions,
            'agentDefinitions' => $this->agentDefinitions,
            'agentVersions' => $this->agentVersions,
            'promptDefinitions' => $this->promptDefinitions,
            'promptVersions' => $this->promptVersions,
            'activations' => $this->activations,
            'gaps' => $this->gaps,
            'conflicts' => $this->conflicts,
        ];
    }
}
