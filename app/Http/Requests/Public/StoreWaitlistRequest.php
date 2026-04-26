<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'customer_name' => ['nullable', 'string', 'max:255', 'regex:/.*\S.*/'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s+\-()]{7,20}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenant = $this->route('tenant');
            $tenantId = $tenant instanceof Tenant
                ? (string) $tenant->getKey()
                : $this->resolveTenantIdFromSlug((string) $tenant);

            if ($tenantId === null) {
                return;
            }

            $serviceTenantId = Service::query()
                ->withoutGlobalScopes()
                ->whereKey($this->input('service_id'))
                ->value('tenant_id');

            if ($serviceTenantId !== null && (string) $serviceTenantId !== $tenantId) {
                $validator->errors()->add('service_id', 'El servicio seleccionado no pertenece a este negocio.');
            }
        });
    }

    private function resolveTenantIdFromSlug(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        $tenantId = Tenant::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->value('id');

        return $tenantId !== null ? (string) $tenantId : null;
    }
}
