<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use InvalidArgumentException;

final readonly class WorkforceRoleConfiguration
{
    /**
     * @param list<string> $allowedJobs
     * @param list<string> $metricKeys
     */
    public function __construct(
        public AgentRole $role,
        public string $objective,
        public array $allowedJobs,
        public string $riskProfile,
        public array $metricKeys,
    ) {
        if (trim($objective) === '' || trim($riskProfile) === '') {
            throw new InvalidArgumentException('Workforce role configuration requires an objective and risk profile.');
        }

        foreach (['allowedJobs' => $allowedJobs, 'metricKeys' => $metricKeys] as $name => $values) {
            if (! array_is_list($values) || $values === [] || count(array_unique($values)) !== count($values)) {
                throw new InvalidArgumentException("Workforce {$name} must be a non-empty unique list.");
            }

            foreach ($values as $value) {
                if (! is_string($value) || preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $value) !== 1) {
                    throw new InvalidArgumentException("Workforce {$name} contains an invalid key.");
                }
            }
        }
    }

    /** @return array{role: string, objective: string, allowedJobs: list<string>, riskProfile: string, metricKeys: list<string>} */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'objective' => $this->objective,
            'allowedJobs' => $this->allowedJobs,
            'riskProfile' => $this->riskProfile,
            'metricKeys' => $this->metricKeys,
        ];
    }
}
