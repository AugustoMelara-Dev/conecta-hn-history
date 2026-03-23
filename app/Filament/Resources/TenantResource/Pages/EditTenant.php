<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Filament\Resources\Pages\EditAdminRecord;
use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Actions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EditTenant extends EditAdminRecord
{
    protected static string $resource = TenantResource::class;

    /** @var array<int, string>|null */
    private static ?array $tenantColumns = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Tenant) {
            return parent::handleRecordUpdate($record, $data);
        }

        $previousOwnerId = (string) ($record->getAttribute('user_id') ?? '');

        $record->forceFill($this->normalizeTenantData($data));
        $record->save();

        $this->syncOwner($record, $previousOwnerId, $data['user_id'] ?? null);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTenantData(array $data): array
    {
        $settings = app(GeneralSettings::class);
        $isPro = (bool) ($data['is_pro'] ?? false);
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
            'is_pro' => $isPro,
            'plan_type' => $isPro ? TenantPlan::PRO->value : TenantPlan::SEMILLA->value,
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
        ]);
    }

    private function syncOwner(Tenant $tenant, string $previousOwnerId, mixed $nextOwnerId): void
    {
        if ($previousOwnerId !== '' && $previousOwnerId !== (string) $nextOwnerId) {
            $previousOwner = User::query()->find($previousOwnerId);

            if ($previousOwner instanceof User && (string) $previousOwner->tenant_id === (string) $tenant->getKey()) {
                $previousOwner->forceFill(['tenant_id' => null])->save();
            }
        }

        if (! is_string($nextOwnerId) || $nextOwnerId === '') {
            return;
        }

        $nextOwner = User::query()->find($nextOwnerId);

        if (! $nextOwner instanceof User) {
            return;
        }

        $nextOwner->forceFill(['tenant_id' => $tenant->getKey()])->save();
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
