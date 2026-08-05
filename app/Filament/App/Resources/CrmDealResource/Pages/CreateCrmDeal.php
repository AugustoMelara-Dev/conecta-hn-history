<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\Pages;

use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Crm\Commands\CreateOpportunityCommand;
use App\Application\Markets\TenantMarketResolver;
use App\Filament\App\Resources\CrmDealResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCrmDeal extends CreateRecord
{
    protected static string $resource = CrmDealResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = CrmDealResource::currentTenant();
        $actor = Filament::auth()->user();
        if (! $tenant instanceof Tenant || $actor === null) {
            throw new \RuntimeException('CRM opportunity creation requires tenant and actor context.');
        }

        return app(CanonicalOpportunityWriter::class)->create(new CreateOpportunityCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            idempotencyKey: 'crm-filament:create:'.Str::uuid(),
            title: (string) $data['title'],
            valueMinor: $this->minorFromDecimal($tenant, $data['value'] ?? 0, $data['currency'] ?? null),
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            pipelineId: isset($data['crm_pipeline_id']) ? (int) $data['crm_pipeline_id'] : null,
            stageId: isset($data['crm_stage_id']) ? (int) $data['crm_stage_id'] : null,
            customerProfileId: isset($data['customer_profile_id']) ? (int) $data['customer_profile_id'] : null,
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            ownerUserId: isset($data['owner_user_id']) ? (string) $data['owner_user_id'] : null,
            locationId: isset($data['location_id']) ? (string) $data['location_id'] : null,
            source: isset($data['source']) ? (string) $data['source'] : 'filament',
            expectedCloseDate: isset($data['expected_close_at']) ? (string) $data['expected_close_at'] : null,
            lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        ))->deal;
    }

    private function minorFromDecimal(Tenant $tenant, mixed $value, mixed $currency): int
    {
        $currencyCode = strtoupper((string) (
            $currency ?: app(TenantMarketResolver::class)->resolve($tenant)->currencyCode
        ));
        $minorUnit = DB::table('currencies')->where('code', $currencyCode)->value('minor_unit');
        if ($minorUnit === null) {
            throw new \InvalidArgumentException("Currency [{$currencyCode}] is not configured.");
        }

        return (int) round((float) $value * (10 ** (int) $minorUnit));
    }
}
