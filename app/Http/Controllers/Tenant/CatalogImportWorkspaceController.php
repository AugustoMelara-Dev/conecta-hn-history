<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Http\Controllers\Controller;
use App\Models\CatalogImportBatch;
use App\Models\Category;
use App\Models\Tenant;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogImportWorkspaceController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenantContext,
        TenantPermissionGuardInterface $permissionGuard,
        PlanFeatureFlagService $planFeatures,
        CanonicalPanelUrlResolver $panelUrls,
    ): Response {
        $tenant = Tenant::query()
            ->whereKey((string) $tenantContext->id())
            ->where('is_active', true)
            ->firstOrFail();
        abort_unless($permissionGuard->allows(
            $request->user(),
            $tenant,
            Capability::CATALOG_BASIC,
            Permission::CATALOG_VIEW,
        ), 403);

        $aiAvailable = $permissionGuard->allows(
            $request->user(),
            $tenant,
            Capability::AI_CONTENT,
            Permission::AI_USE,
        );
        $latestBatchId = CatalogImportBatch::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereNotIn('status', [
                CatalogImportStatus::Published->value,
                CatalogImportStatus::Rejected->value,
            ])
            ->latest('created_at')
            ->value('id');

        return Inertia::render('CatalogImports/Workspace', [
            'workspace' => [
                'tenant' => [
                    'id' => (string) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'is_pro' => (bool) $tenant->is_pro,
                ],
                'available' => $aiAvailable,
                'limits' => [
                    'products' => $planFeatures->getMaxProductLimit($tenant),
                    'images_per_batch' => min(20, $planFeatures->getMaxMediaUploads($tenant)),
                    'image_size_mb' => 10,
                ],
                'categories' => Category::query()
                    ->active()
                    ->ordered()
                    ->get(['id', 'name'])
                    ->map(static fn (Category $category): array => [
                        'id' => (int) $category->getKey(),
                        'name' => (string) $category->name,
                    ])
                    ->values()
                    ->all(),
                'latest_batch_id' => is_string($latestBatchId) ? $latestBatchId : null,
                'api_url' => '/api/v1/catalog-imports',
                'products_url' => $panelUrls->forTenant($tenant, 'products'),
                'upgrade_url' => $panelUrls->forTenant($tenant, 'upgrade'),
            ],
        ]);
    }
}
