<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge;

use App\Application\Knowledge\CommercialKnowledgeHealth;
use App\Application\Knowledge\CommercialKnowledgeHealthEvaluator;
use App\Application\Knowledge\Contracts\CommercialKnowledgeHealthReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class EloquentCommercialKnowledgeHealthReader implements CommercialKnowledgeHealthReader
{
    public function __construct(private CommercialKnowledgeHealthEvaluator $evaluator) {}

    public function read(string $tenantId): CommercialKnowledgeHealth
    {
        $now = new \DateTimeImmutable;
        if ($tenantId === '') {
            return $this->evaluator->evaluate(['schema_complete' => false], $now);
        }

        $required = [
            'tenants' => ['id', 'slug', 'commercial_mode', 'updated_at'],
            'products' => ['tenant_id', 'is_visible', 'price', 'price_minor', 'updated_at'],
            'services' => ['tenant_id', 'is_active', 'price', 'price_minor', 'updated_at'],
            'opening_hours' => ['tenant_id', 'opening_time', 'closing_time'],
            'location_service_areas' => ['tenant_id', 'is_active', 'updated_at'],
        ];
        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                return $this->evaluator->evaluate(['schema_complete' => false], $now);
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return $this->evaluator->evaluate(['schema_complete' => false], $now);
                }
            }
        }

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if ($tenant === null) {
            return $this->evaluator->evaluate(['schema_complete' => false], $now);
        }

        $products = DB::table('products')->where('tenant_id', $tenantId)->where('is_visible', true)
            ->when(Schema::hasColumn('products', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));
        $services = DB::table('services')->where('tenant_id', $tenantId)->where('is_active', true);
        $hasPublicProduct = (clone $products)->exists();
        $hasPublicService = (clone $services)->exists();
        $hasPricedProduct = (clone $products)->where(function ($query): void {
            $query->whereNotNull('price_minor')->orWhereNotNull('price');
        })->exists();
        $hasPricedService = (clone $services)->where(function ($query): void {
            $query->whereNotNull('price_minor')->orWhereNotNull('price');
        })->exists();

        $hasHours = DB::table('opening_hours')->where('tenant_id', $tenantId)
            ->whereNotNull('opening_time')->whereNotNull('closing_time')->exists();
        $hasCoverage = DB::table('location_service_areas')->where('tenant_id', $tenantId)->where('is_active', true)->exists();
        $proposition = trim(implode(' ', array_filter([(string) ($tenant->description ?? ''), (string) ($tenant->about ?? ''), (string) ($tenant->slogan ?? '')])));
        $hasContact = collect(['email', 'phone', 'whatsapp_number', 'website_url'])
            ->contains(fn (string $field): bool => trim((string) ($tenant->{$field} ?? '')) !== '');
        $timestamps = array_filter([
            (string) ($tenant->updated_at ?? ''),
            (string) ((clone $products)->max('updated_at') ?? ''),
            (string) ((clone $services)->max('updated_at') ?? ''),
            (string) (DB::table('location_service_areas')->where('tenant_id', $tenantId)->max('updated_at') ?? ''),
        ]);

        return $this->evaluator->evaluate([
            'schema_complete' => true,
            'tenant_slug' => (string) $tenant->slug,
            'playbook' => is_object($tenant->commercial_mode) && isset($tenant->commercial_mode->value)
                ? $tenant->commercial_mode->value : (string) ($tenant->commercial_mode ?? 'products'),
            'has_public_product' => $hasPublicProduct,
            'has_priced_product' => $hasPricedProduct,
            'has_public_service' => $hasPublicService,
            'has_priced_service' => $hasPricedService,
            'has_usable_hours' => $hasHours,
            'has_compatible_coverage' => $hasCoverage,
            'has_public_proposition' => $proposition !== '',
            'has_contact_channel' => $hasContact,
            'relevant_fact_updated_at' => $timestamps === [] ? null : max($timestamps),
        ], $now);
    }
}
