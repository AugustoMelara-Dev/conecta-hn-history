<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'string'],
            'lifecycle_stage' => ['nullable', 'string', 'in:lead,qualified,proposal,negotiation,won,lost'],
            'source' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
