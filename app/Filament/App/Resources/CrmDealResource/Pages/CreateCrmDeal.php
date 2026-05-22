<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\Pages;

use App\Filament\App\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\CrmStage;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmDeal extends CreateRecord
{
    protected static string $resource = CrmDealResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = CrmDealResource::currentTenant();
        $pipeline = CrmDealResource::defaultPipeline();
        $stage = isset($data['crm_stage_id'])
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ? CrmStage::query()
                ->withoutGlobalScopes()
                ->when($tenant !== null, fn ($query) => $query->where('tenant_id', (string) $tenant->getKey()))
                ->find($data['crm_stage_id'])
            : CrmDealResource::defaultStage();

        if ($tenant !== null) {
            $data['tenant_id'] = (string) $tenant->getKey();
        }

        if ($pipeline !== null && empty($data['crm_pipeline_id'])) {
            $data['crm_pipeline_id'] = $pipeline->getKey();
        }

        if ($stage instanceof CrmStage) {
            $data['crm_stage_id'] = $stage->getKey();
            $data['status'] = match ($stage->type) {
                CrmStage::TYPE_WON => CrmDeal::STATUS_WON,
                CrmStage::TYPE_LOST => CrmDeal::STATUS_LOST,
                default => $data['status'] ?? CrmDeal::STATUS_OPEN,
            };
            $data['closed_at'] = in_array($data['status'], [CrmDeal::STATUS_WON, CrmDeal::STATUS_LOST], true)
                ? now()
                : null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenantId = $data['tenant_id'] ?? null;
        unset($data['tenant_id']);

        $record = new CrmDeal;
        $record->fill($data);

        if ($tenantId !== null && $tenantId !== '') {
            $record->forceFill(['tenant_id' => (string) $tenantId]);
        }

        $record->save();

        return $record;
    }
}
