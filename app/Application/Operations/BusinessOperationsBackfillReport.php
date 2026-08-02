<?php

declare(strict_types=1);

namespace App\Application\Operations;

final readonly class BusinessOperationsBackfillReport
{
    /**
     * @param  list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>  $gaps
     * @param  list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>  $conflicts
     */
    public function __construct(
        public int $productsMapped,
        public int $servicesMapped,
        public int $providersMapped,
        public int $appointmentsMapped,
        public int $dealsMapped,
        public int $availabilityRulesTotal,
        public int $locationsMapped,
        public array $gaps,
        public array $conflicts,
    ) {}

    /**
     * @return array{
     *     products_mapped: int,
     *     services_mapped: int,
     *     providers_mapped: int,
     *     appointments_mapped: int,
     *     deals_mapped: int,
     *     availability_rules_total: int,
     *     locations_mapped: int,
     *     gaps: list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>,
     *     conflicts: list<array{code: string, tenant_id: string, resource_type: string, resource_id: string|null, detail: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'products_mapped' => $this->productsMapped,
            'services_mapped' => $this->servicesMapped,
            'providers_mapped' => $this->providersMapped,
            'appointments_mapped' => $this->appointmentsMapped,
            'deals_mapped' => $this->dealsMapped,
            'availability_rules_total' => $this->availabilityRulesTotal,
            'locations_mapped' => $this->locationsMapped,
            'gaps' => $this->gaps,
            'conflicts' => $this->conflicts,
        ];
    }
}
