<?php

declare(strict_types=1);

namespace App\Application\Knowledge;

final class CommercialKnowledgeHealthEvaluator
{
    /** @param array<string, mixed> $facts */
    public function evaluate(array $facts, \DateTimeImmutable $evaluatedAt): CommercialKnowledgeHealth
    {
        $playbook = in_array($facts['playbook'] ?? null, ['products', 'appointments', 'consulting_leads', 'hybrid'], true)
            ? (string) $facts['playbook'] : 'products';
        $slug = is_string($facts['tenant_slug'] ?? null) && $facts['tenant_slug'] !== '' ? $facts['tenant_slug'] : 'negocio';
        $issues = [];

        if (($facts['schema_complete'] ?? false) !== true) {
            $this->add($issues, 'knowledge_unavailable', 'La verificacion comercial no esta disponible hasta aplicar las migraciones pendientes', '#', 'blocking');

            return new CommercialKnowledgeHealth(0, false, $issues, $evaluatedAt);
        }

        $needsProducts = in_array($playbook, ['products', 'hybrid'], true);
        $needsServices = in_array($playbook, ['appointments', 'hybrid'], true);
        if ($playbook === 'consulting_leads') {
            if (($facts['has_public_proposition'] ?? false) !== true || ($facts['has_contact_channel'] ?? false) !== true) {
                $this->add($issues, 'missing_offer', 'Explica tu propuesta y publica un canal de contacto', "/app/{$slug}/profile", 'blocking');
            }
        } else {
            if ($needsProducts && ($facts['has_public_product'] ?? false) !== true) {
                $this->add($issues, 'missing_product', 'Publica al menos un producto disponible', "/app/{$slug}/products/create", 'blocking');
            }
            if ($needsProducts && ($facts['has_priced_product'] ?? false) !== true) {
                $this->add($issues, 'missing_product_price', 'Agrega un precio verificable a un producto publico', "/app/{$slug}/products", 'blocking');
            }
            if ($needsServices && ($facts['has_public_service'] ?? false) !== true) {
                $this->add($issues, 'missing_service', 'Publica al menos un servicio disponible', "/app/{$slug}/services/create", 'blocking');
            }
            if ($needsServices && ($facts['has_priced_service'] ?? false) !== true) {
                $this->add($issues, 'missing_service_price', 'Agrega un precio verificable a un servicio publico', "/app/{$slug}/services", 'blocking');
            }
        }

        if ($needsServices && ($facts['has_usable_hours'] ?? false) !== true) {
            $this->add($issues, 'missing_hours', 'Define horarios para atender clientes', "/app/{$slug}/profile", 'blocking');
        }
        if (($facts['has_compatible_coverage'] ?? false) !== true) {
            $this->add($issues, 'missing_coverage', 'Indica donde atiendes cada modalidad', "/app/{$slug}/manage-coverage", 'blocking');
        }

        $latest = $this->date($facts['relevant_fact_updated_at'] ?? null);
        if ($latest !== null && $latest < $evaluatedAt->modify('-90 days')) {
            $this->add($issues, 'stale_facts', 'Revisa que la informacion comercial siga vigente', "/app/{$slug}/profile", 'warning');
        }

        $score = 100;
        foreach ($issues as $issue) {
            $score -= $issue['severity'] === 'blocking' ? 20 : 10;
        }

        return new CommercialKnowledgeHealth(
            max(0, $score),
            ! array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'blocking'),
            $issues,
            $evaluatedAt,
        );
    }

    /** @param list<array{code:string,label:string,action_url:string,severity:string}> $issues */
    private function add(array &$issues, string $code, string $label, string $url, string $severity): void
    {
        $issues[] = ['code' => $code, 'label' => $label, 'action_url' => $url, 'severity' => $severity];
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        try {
            return is_string($value) && $value !== '' ? new \DateTimeImmutable($value) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
