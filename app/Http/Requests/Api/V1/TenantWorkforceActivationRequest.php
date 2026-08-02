<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TenantWorkforceActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant permission and AI capability checks require the resolved
        // tenant context and are enforced by TenantWorkforceController.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role_key' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (AgentRole $role): string => $role->value,
                    AgentRoleCatalog::roles(),
                )),
            ],
            'channel_keys' => ['nullable', 'array'],
            'channel_keys.*' => ['string', 'max:128'],
            'knowledge_source_ids' => ['nullable', 'array'],
            'knowledge_source_ids.*' => ['string', 'max:255'],
            'simulation_case_ids' => ['nullable', 'array'],
            'simulation_case_ids.*' => ['string', 'max:255'],
            'approval_mode' => ['required', 'string', Rule::in(['human_review', 'policy_gate'])],
        ];
    }
}
