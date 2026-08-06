<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_id' => ['sometimes', 'nullable', 'string', 'exists:crm_companies,id'],
            'owner_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'lifecycle_stage' => ['sometimes', 'nullable', 'string', 'in:lead,qualified,proposal,negotiation,won,lost'],
            'source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
