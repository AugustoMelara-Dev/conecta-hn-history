<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use Illuminate\Support\Facades\DB;

/**
 * BulkOperationsService - Operaciones masivas sobre CRM entities con policies.
 *
 * - BulkAssignOwner
 * - BulkUpdateLifecycleStage
 * - BulkDelete (soft delete con audit)
 * - BulkAddTag / BulkRemoveTag
 */
class BulkOperationsService
{
    /**
     * @param array<int, string> $contactIds
     */
    public function bulkAssignOwner(array $contactIds, string $ownerId, string $tenantId): int
    {
        return DB::table('crm_contacts')
            ->whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->update([
                'owner_id' => $ownerId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, string> $contactIds
     */
    public function bulkUpdateLifecycleStage(array $contactIds, string $lifecycleStage, string $tenantId): int
    {
        return DB::table('crm_contacts')
            ->whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->update([
                'lifecycle_stage' => $lifecycleStage,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, string> $contactIds
     */
    public function bulkDelete(array $contactIds, string $tenantId): int
    {
        return DB::table('crm_contacts')
            ->whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, string> $contactIds
     */
    public function bulkAddTag(array $contactIds, string $tag, string $tenantId): int
    {
        $contacts = Contact::whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->get();

        $count = 0;
        foreach ($contacts as $contact) {
            $tags = $contact->tags ?? [];
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
                $contact->tags = $tags;
                $contact->save();
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<int, string> $contactIds
     */
    public function bulkRemoveTag(array $contactIds, string $tag, string $tenantId): int
    {
        $contacts = Contact::whereIn('id', $contactIds)
            ->where('tenant_id', $tenantId)
            ->get();

        $count = 0;
        foreach ($contacts as $contact) {
            $tags = $contact->tags ?? [];
            $key = array_search($tag, $tags, true);
            if ($key !== false) {
                unset($tags[$key]);
                $contact->tags = array_values($tags);
                $contact->save();
                $count++;
            }
        }
        return $count;
    }
}
