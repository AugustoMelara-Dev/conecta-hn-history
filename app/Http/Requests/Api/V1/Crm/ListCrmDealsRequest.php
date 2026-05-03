<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ListCrmDealsRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_VIEW);
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:open,won,lost',
            'stage_id' => 'nullable|integer',
            'owner_user_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
