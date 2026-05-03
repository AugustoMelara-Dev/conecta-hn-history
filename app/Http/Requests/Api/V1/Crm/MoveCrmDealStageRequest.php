<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class MoveCrmDealStageRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_MOVE_STAGE);
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'required|integer',
        ];
    }
}
