<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use App\Models\CrmDeal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportCrmDealsRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_CREATE);
    }

    public function rules(): array
    {
        return [
            'deals' => 'required|array|min:1|max:100',
            'deals.*.pipeline_id' => 'nullable|integer',
            'deals.*.stage_id' => 'nullable|integer',
            'deals.*.customer_profile_id' => 'nullable|integer',
            'deals.*.lead_id' => 'nullable|integer',
            'deals.*.owner_user_id' => 'nullable|uuid',
            'deals.*.location_id' => 'nullable|uuid',
            'deals.*.title' => 'required|string|max:255',
            'deals.*.value' => 'nullable|numeric|min:0|max:9999999999.99',
            'deals.*.currency' => 'nullable|string|size:3',
            'deals.*.status' => ['nullable', 'string', Rule::in([CrmDeal::STATUS_OPEN, CrmDeal::STATUS_WON, CrmDeal::STATUS_LOST])],
            'deals.*.source' => 'nullable|string|max:255',
            'deals.*.expected_close_at' => 'nullable|date_format:Y-m-d',
            'deals.*.closed_at' => 'nullable|date',
            'deals.*.lost_reason' => 'nullable|string|max:255',
            'deals.*.notes' => 'nullable|string|max:5000',
        ];
    }
}
