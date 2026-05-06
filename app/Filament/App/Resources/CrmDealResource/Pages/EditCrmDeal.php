<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\Pages;

use App\Filament\App\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\CrmStage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmDeal extends EditRecord
{
    protected static string $resource = CrmDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (CrmDeal $record): bool => CrmDealResource::canDelete($record)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $stage = isset($data['crm_stage_id'])
            ? CrmStage::query()->withoutGlobalScopes()->find($data['crm_stage_id'])
            : null;

        if ($stage instanceof CrmStage) {
            $data['status'] = match ($stage->type) {
                CrmStage::TYPE_WON => CrmDeal::STATUS_WON,
                CrmStage::TYPE_LOST => CrmDeal::STATUS_LOST,
                default => $data['status'] ?? CrmDeal::STATUS_OPEN,
            };
        }

        $isClosed = in_array($data['status'] ?? null, [CrmDeal::STATUS_WON, CrmDeal::STATUS_LOST], true);
        $wasClosed = $this->record instanceof CrmDeal && $this->record->closed_at !== null;
        $data['closed_at'] = $isClosed ? ($this->record->closed_at ?? now()) : null;

        if (! $isClosed && $wasClosed) {
            $data['lost_reason'] = null;
        }

        return $data;
    }
}
