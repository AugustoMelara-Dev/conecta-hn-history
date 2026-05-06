<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCrmPipelineRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_UPDATE);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'key' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ];
    }
}
