<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ExportCrmDealsRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        // Export can expose customer context, so keep it above read-only viewer access.
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_UPDATE);
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:open,won,lost',
            'pipeline_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
        ];
    }
}
