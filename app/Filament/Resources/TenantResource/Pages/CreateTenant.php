<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Filament\Resources\Pages\CreateAdminRecord;
use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CreateTenant extends CreateAdminRecord
{
    protected static string $resource = TenantResource::class;

    /** @var array<int, string>|null */
    private static ?array $tenantColumns = null;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = new Tenant();
        $tenant->forceFill($this->normalizeTenantData($data));
        $tenant->save();

        $this->syncOwner($tenant, $data['user_id'] ?? null);

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTenantData(array $data): array
    {
        $settings = app(GeneralSettings::class);
        $planType = (string) ($data['plan_type'] ?? TenantPlan::SEMILLA->value);
        $isPro = in_array($planType, [TenantPlan::PRO->value, Tenant::PLAN_CRECIMIENTO, Tenant::PLAN_COSECHA], true);
        $explicitLimit = $data['product_limit'] ?? null;

        return $this->filterTenantColumns([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'plan_type' => $planType,
            'product_limit' => is_numeric($explicitLimit)
                ? (int) $explicitLimit
                : (int) ($isPro ? $settings->pro_plan_product_limit : $settings->free_plan_product_limit),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'status' => 'approved',
            'subscription_status' => 'active',
            'primary_color' => $data['primary_color'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'social_links' => $data['social_links'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'banner_path' => $data['banner_path'] ?? null,
            'brand_color' => $data['brand_color'] ?? null,
            'hero_image' => $data['hero_image'] ?? null,
            'slogan' => $data['slogan'] ?? null,
            'payment_methods' => $data['payment_methods'] ?? null,
            'delivery_enabled' => $data['delivery_enabled'] ?? null,
            'pickup_enabled' => $data['pickup_enabled'] ?? null,
            'minimum_order' => $data['minimum_order'] ?? null,
            'crm_owner_id' => $data['crm_owner_id'] ?? null,
            'crm_team' => $data['crm_team'] ?? Tenant::CRM_TEAM_ACTIVATION,
            'crm_status' => $data['crm_status'] ?? Tenant::CRM_STATUS_NEW,
            'crm_priority' => $data['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL,
            'crm_sla_hours' => $data['crm_sla_hours'] ?? Tenant::DEFAULT_CRM_SLA_HOURS,
            'crm_next_follow_up_at' => $data['crm_next_follow_up_at'] ?? null,
            'crm_notes' => $data['crm_notes'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function syncOwner(Tenant $tenant, mixed $userId): void
    {
        if (! is_string($userId) || $userId === '') {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['tenant_id' => $tenant->getKey()])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterTenantColumns(array $payload): array
    {
        self::$tenantColumns ??= Schema::getColumnListing((new Tenant())->getTable());

        return array_intersect_key($payload, array_flip(self::$tenantColumns));
    }
}
