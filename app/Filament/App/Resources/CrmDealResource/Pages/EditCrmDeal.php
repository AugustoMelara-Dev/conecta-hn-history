<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\Pages;

use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Crm\Commands\MoveOpportunityStageCommand;
use App\Application\Crm\Commands\UpdateOpportunityCommand;
use App\Application\Markets\TenantMarketResolver;
use App\Filament\App\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditCrmDeal extends EditRecord
{
    protected static string $resource = CrmDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (CrmDeal $record): bool => CrmDealResource::canDelete($record))
                ->action(function (CrmDeal $record): void {
                    [$tenant, $actor] = $this->authority();
                    app(CanonicalOpportunityWriter::class)->archive(
                        (string) $tenant->getKey(),
                        (string) $actor->getKey(),
                        (int) $record->getKey(),
                    );
                    $this->redirect(CrmDealResource::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CrmDeal) {
            throw new \InvalidArgumentException('CRM edit requires an opportunity record.');
        }
        [$tenant, $actor] = $this->authority();
        $writer = app(CanonicalOpportunityWriter::class);
        $result = $writer->update(new UpdateOpportunityCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            dealId: (int) $record->getKey(),
            title: isset($data['title']) ? (string) $data['title'] : null,
            valueMinor: array_key_exists('value', $data)
                ? $this->minorFromDecimal($tenant, $data['value'], $data['currency'] ?? $record->currency)
                : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            customerProfileId: isset($data['customer_profile_id']) ? (int) $data['customer_profile_id'] : null,
            customerProfileSpecified: array_key_exists('customer_profile_id', $data),
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            leadSpecified: array_key_exists('lead_id', $data),
            ownerUserId: isset($data['owner_user_id']) ? (string) $data['owner_user_id'] : null,
            ownerSpecified: array_key_exists('owner_user_id', $data),
            locationId: isset($data['location_id']) ? (string) $data['location_id'] : null,
            locationSpecified: array_key_exists('location_id', $data),
            source: isset($data['source']) ? (string) $data['source'] : null,
            sourceSpecified: array_key_exists('source', $data),
            expectedCloseDate: isset($data['expected_close_at']) ? (string) $data['expected_close_at'] : null,
            expectedCloseDateSpecified: array_key_exists('expected_close_at', $data),
            lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            lostReasonSpecified: array_key_exists('lost_reason', $data),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            notesSpecified: array_key_exists('notes', $data),
        ));

        if (isset($data['crm_stage_id'])
            && (int) $data['crm_stage_id'] !== (int) $record->crm_stage_id) {
            $result = $writer->moveStage(new MoveOpportunityStageCommand(
                tenantId: (string) $tenant->getKey(),
                actorId: (string) $actor->getKey(),
                dealId: (int) $record->getKey(),
                stageId: (int) $data['crm_stage_id'],
                lostReason: isset($data['lost_reason']) ? (string) $data['lost_reason'] : null,
            ));
        }

        return $result->deal;
    }

    /**
     * @return array{Tenant, User}
     */
    private function authority(): array
    {
        $tenant = CrmDealResource::currentTenant();
        $actor = Filament::auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('CRM opportunity mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
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
