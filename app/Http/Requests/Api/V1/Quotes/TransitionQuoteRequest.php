<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Quotes;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionQuoteRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(Permission::CRM_DEALS_UPDATE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string|max:160',
            'target_status' => [
                'required',
                'string',
                Rule::in([
                    Quote::STATUS_ISSUED,
                    Quote::STATUS_ACCEPTED,
                    Quote::STATUS_REJECTED,
                    Quote::STATUS_CANCELLED,
                    Quote::STATUS_EXPIRED,
                    Quote::ACTION_CONVERT_TO_ORDER,
                    Quote::ACTION_CONVERT_TO_RESERVATION,
                ]),
            ],
            'reason' => 'nullable|string|max:2000',
            'metadata' => 'nullable|array',
        ];
    }
}
