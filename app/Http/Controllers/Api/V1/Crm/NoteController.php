<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Models\CustomerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CustomerNote::class);
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-Id');
        $query = CustomerNote::query()->where('tenant_id', $tenantId);

        if ($contactId = $request->query('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($dealId = $request->query('deal_id')) {
            $query->where('deal_id', $dealId);
        }

        return response()->json($query->paginate(min((int) $request->query('per_page', 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CustomerNote::class);
        $data = $request->validate([
            'contact_id' => ['nullable', 'string', 'exists:crm_contacts,id'],
            'deal_id' => ['nullable', 'string', 'exists:crm_deals,id'],
            'type' => ['nullable', 'in:general,call_summary,meeting,follow_up'],
            'body' => ['required', 'string'],
        ]);
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['created_by'] = auth()->id();

        return response()->json(CustomerNote::create($data), 201);
    }

    public function show(CustomerNote $note): JsonResponse
    {
        Gate::authorize('view', $note);

        return response()->json($note);
    }

    public function update(Request $request, CustomerNote $note): JsonResponse
    {
        Gate::authorize('update', $note);
        $data = $request->validate([
            'type' => ['sometimes', 'in:general,call_summary,meeting,follow_up'],
            'body' => ['sometimes', 'string'],
        ]);
        $note->update($data);

        return response()->json($note);
    }

    public function destroy(CustomerNote $note): JsonResponse
    {
        Gate::authorize('delete', $note);
        $note->delete();

        return response()->json(['message' => 'Note deleted successfully']);
    }
}
