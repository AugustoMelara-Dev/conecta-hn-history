<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Models\CrmCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmCompany::class);
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-Id');
        $query = CrmCompany::query()->where('tenant_id', $tenantId);

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }
        if ($industry = $request->query('industry')) {
            $query->where('industry', $industry);
        }
        if ($size = $request->query('size')) {
            $query->where('size', $size);
        }

        $perPage = (int) $request->query('per_page', 25);

        return response()->json($query->paginate(min($perPage, 100)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CrmCompany::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'in:micro,small,medium,large,enterprise'],
            'website' => ['nullable', 'url'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['tenant_id'] = $request->user()->tenant_id;
        $company = CrmCompany::create($data);

        return response()->json($company, 201);
    }

    public function show(CrmCompany $company): JsonResponse
    {
        Gate::authorize('view', $company);

        return response()->json($company);
    }

    public function update(Request $request, CrmCompany $company): JsonResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'size' => ['sometimes', 'nullable', 'in:micro,small,medium,large,enterprise'],
            'website' => ['sometimes', 'nullable', 'url'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
        $company->update($data);

        return response()->json($company);
    }

    public function destroy(CrmCompany $company): JsonResponse
    {
        Gate::authorize('delete', $company);
        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
