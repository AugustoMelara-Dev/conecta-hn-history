<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use App\Models\CrmStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCrmStageRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_UPDATE);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'key' => 'sometimes|required|string|max:120',
            'type' => ['sometimes', 'string', Rule::in([CrmStage::TYPE_OPEN, CrmStage::TYPE_WON, CrmStage::TYPE_LOST])],
            'probability' => 'sometimes|integer|min:0|max:100',
            'is_default' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ];
    }
}
