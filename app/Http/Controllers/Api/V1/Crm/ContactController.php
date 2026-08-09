<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Requests\Crm\StoreContactRequest;
use App\Http\Requests\Crm\UpdateContactRequest;
use App\Http\Resources\Crm\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Contact::class);

        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-Id');
        $query = Contact::query()->where('tenant_id', $tenantId);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($stage = $request->query('lifecycle_stage')) {
            $query->where('lifecycle_stage', $stage);
        }

        $perPage = (int) $request->query('per_page', 25);
        $contacts = $query->paginate(min($perPage, 100));

        return ContactResource::collection($contacts)->response();
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);
        $contact = Contact::create($request->validated() + ['tenant_id' => $request->user()->tenant_id]);

        return (new ContactResource($contact))->response()->setStatusCode(201);
    }

    public function show(Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        return (new ContactResource($contact))->response();
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        Gate::authorize('update', $contact);
        $contact->update($request->validated());

        return (new ContactResource($contact))->response();
    }

    public function destroy(Contact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->json(['message' => 'Contact deleted successfully']);
    }
}
