<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCrmDealRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_CREATE);
    }

    public function rules(): array
    {
        return [
            'pipeline_id' => 'required|integer',
            'stage_id' => 'required|integer',
            'customer_profile_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'owner_user_id' => 'nullable|uuid',
            'location_id' => 'nullable|uuid',
            'title' => 'required|string|max:255',
            'value' => 'nullable|numeric|min:0|max:9999999999.99',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|in:open,won,lost',
            'source' => 'nullable|string|max:255',
            'expected_close_at' => 'nullable|date_format:Y-m-d',
            'closed_at' => 'nullable|date',
            'lost_reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
