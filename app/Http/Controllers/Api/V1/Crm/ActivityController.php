<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Models\CrmActivity;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function __construct()
    {
        Gate::policy(CrmActivity::class, ActivityPolicy::class);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmActivity::class);
        $tenantId = $this->currentTenantId();
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
            'contact_id' => ['nullable', 'string'],
            'deal_id' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $activity = app(CanonicalCrmEntityWriter::class)->createActivity(
            $this->currentTenantId(),
            $this->actorId($request),
            $data,
        );

        return response()->json($activity, 201);
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
        $activity = app(CanonicalCrmEntityWriter::class)->updateActivity(
            $this->currentTenantId(),
            $this->actorId($request),
            (string) $activity->getKey(),
            $data,
        );

        return response()->json($activity);
    }

    public function destroy(CrmActivity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);
        $activity->delete();

        return response()->json(['message' => 'Activity deleted successfully']);
    }

    private function currentTenantId(): string
    {
        abort_unless(app()->bound('currentTenant'), 403);

        $tenant = app('currentTenant');
        abort_unless($tenant instanceof Tenant && (bool) $tenant->is_active, 403);

        return (string) $tenant->getKey();
    }

    private function actorId(Request $request): string
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return (string) $actor->getKey();
    }
}
