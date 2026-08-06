<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Models\CrmActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmActivity::class);
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-Id');
        $query = CrmActivity::where('tenant_id', $tenantId);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }

        return response()->json($query->paginate(min((int) $request->query('per_page', 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CrmActivity::class);
        $data = $request->validate([
            'type' => ['required', 'string', 'in:call,email,meeting,task,note,sms,whatsapp'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'string', 'exists:crm_contacts,id'],
            'deal_id' => ['nullable', 'string', 'exists:crm_deals,id'],
            'owner_id' => ['nullable', 'string', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);
        $data['tenant_id'] = $request->user()->tenant_id;
        return response()->json(CrmActivity::create($data), 201);
    }

    public function show(CrmActivity $activity): JsonResponse
    {
        Gate::authorize('view', $activity);
        return response()->json($activity);
    }

    public function update(Request $request, CrmActivity $activity): JsonResponse
    {
        Gate::authorize('update', $activity);
        $data = $request->validate([
            'type' => ['sometimes', 'string', 'in:call,email,meeting,task,note,sms,whatsapp'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:3'],
        ]);
        $activity->update($data);
        return response()->json($activity);
    }

    public function destroy(CrmActivity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);
        $activity->delete();
        return response()->json(['message' => 'Activity deleted successfully']);
    }
}
