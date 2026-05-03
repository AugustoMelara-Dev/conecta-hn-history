<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Crm;

use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Lead;

final class CrmCapabilityMap
{
    public const CONTACTS = 'contacts';
    public const LEADS = 'leads';
    public const ACTIVITIES = 'activities';
    public const CAMPAIGNS = 'campaigns';
    public const PIPELINES = 'pipelines';
    public const DEALS = 'deals';
    public const STAGES = 'stages';
    public const CRM_API = 'crm_api';

    /** @return array<string, array{status:string, canonical_model:?class-string, description:string}> */
    public function capabilities(): array
    {
        return [
            self::CONTACTS => [
                'status' => 'supported',
                'canonical_model' => CustomerProfile::class,
                'description' => 'CustomerProfile is the canonical customer/contact aggregate for a tenant.',
            ],
            self::LEADS => [
                'status' => 'supported',
                'canonical_model' => Lead::class,
                'description' => 'Lead is an inbound inquiry or prospect signal, not a deal.',
            ],
            self::ACTIVITIES => [
                'status' => 'partial',
                'canonical_model' => CustomerTouchpoint::class,
                'description' => 'Activity history is spread across touchpoints, notes and tasks.',
            ],
            self::CAMPAIGNS => [
                'status' => 'partial',
                'canonical_model' => null,
                'description' => 'Campaigns exist for segmented outbound follow-up, but they are not a pipeline engine.',
            ],
            self::PIPELINES => [
                'status' => 'gated',
                'canonical_model' => CrmPipeline::class,
                'description' => 'CrmPipeline is available behind TenantFeature::CRM_PIPELINES.',
            ],
            self::DEALS => [
                'status' => 'gated',
                'canonical_model' => CrmDeal::class,
                'description' => 'CrmDeal is the canonical opportunity model behind the CRM pipeline gate.',
            ],
            self::STAGES => [
                'status' => 'gated',
                'canonical_model' => CrmStage::class,
                'description' => 'CrmStage is the canonical configurable pipeline stage model behind the CRM pipeline gate.',
            ],
            self::CRM_API => [
                'status' => 'gated_partial',
                'canonical_model' => null,
                'description' => 'The first CRM v1 API contract exists for gated pipelines, deals and summary reporting.',
            ],
        ];
    }

    /** @return array<string, array{status:string, canonical_model:?class-string, description:string}> */
    public function missingCapabilities(): array
    {
        return array_filter(
            $this->capabilities(),
            static fn (array $capability): bool => $capability['status'] === 'missing',
        );
    }

    /** @return list<class-string> */
    public function activityModels(): array
    {
        return [
            CustomerTouchpoint::class,
            CustomerNote::class,
            CustomerTask::class,
        ];
    }
}
