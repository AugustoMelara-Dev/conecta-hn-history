<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Quotes;

use App\Domain\Access\Enums\Permission;
use App\Http\Requests\Api\V1\Crm\Concerns\AuthorizesCrmPipelineAccess;
use Illuminate\Foundation\Http\FormRequest;

final class StoreQuoteRequest extends FormRequest
{
    use AuthorizesCrmPipelineAccess;

    public function authorize(): bool
    {
        return $this->authorizeCrmPipeline(
            $this->routeIs('api.quotes.revise')
                ? Permission::CRM_DEALS_UPDATE
                : Permission::CRM_DEALS_CREATE,
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string|max:160',
            'location_id' => $this->routeIs('api.quotes.revise')
                ? 'nullable|uuid'
                : 'required|uuid',
            'customer_profile_id' => 'nullable|integer|min:1',
            'lead_id' => 'nullable|integer|min:1',
            'crm_deal_id' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:48',
            'expires_at' => 'nullable|date',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'nullable|integer|min:1',
            'items.*.service_id' => 'nullable|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.discount_minor' => 'nullable|integer|min:0',
            'items.*.tax_minor' => 'nullable|integer|min:0',
            'items.*.metadata' => 'nullable|array',
        ];
    }
}
