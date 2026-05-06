<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ConvertLeadToCrmDealRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_CREATE);
    }

    public function rules(): array
    {
        return [
            'pipeline_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'value' => 'nullable|numeric|min:0|max:9999999999.99',
            'currency' => 'nullable|string|size:3',
            'owner_user_id' => 'nullable|uuid',
            'expected_close_at' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
