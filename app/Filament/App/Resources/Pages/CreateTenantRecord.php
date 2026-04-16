<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Pages;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class CreateTenantRecord extends CreateRecord
{
    public function getTitle(): string
    {
        return 'Crear '.Str::lower($this->getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    protected function getHeaderWidgets(): array
    {
        request()->attributes->set('onboarding_module', $this->onboardingModuleContext());

        return [
            \App\Filament\App\Widgets\OnboardingActivationWidget::class,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        unset(
            $data['id'],
            $data['tenant_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['deleted_at'],
        );

        $record = new ($this->getModel())();
        $record->forceFill($data);
        $tenant = Filament::getTenant();

        if (
            $tenant instanceof Model &&
            in_array(BelongsToTenant::class, class_uses_recursive($record), true)
        ) {
            $record->forceFill([
                'tenant_id' => $tenant->getKey(),
            ]);
        }

        if ($parentRecord = $this->getParentRecord()) {
            return $this->associateRecordWithParent($record, $parentRecord);
        }

        $record->save();

        return $record;
    }

    protected function onboardingModuleContext(): string
    {
        return match (true) {
            str_contains(static::class, 'OrderResource'),
            str_contains(static::class, 'OperationalPlaybookTaskResource') => 'orders',
            str_contains(static::class, 'AppointmentResource') => 'agenda',
            str_contains(static::class, 'CampaignResource'),
            str_contains(static::class, 'AnnouncementResource'),
            str_contains(static::class, 'CustomerMessageTemplateResource') => 'marketing',
            str_contains(static::class, 'TenantUserResource') => 'team',
            str_contains(static::class, 'ProductResource'),
            str_contains(static::class, 'ServiceResource'),
            str_contains(static::class, 'ServiceProviderResource'),
            str_contains(static::class, 'MenuSectionResource'),
            str_contains(static::class, 'CouponResource'),
            str_contains(static::class, 'LoyaltyProgramResource'),
            str_contains(static::class, 'LoyaltyRewardResource') => 'catalog',
            default => 'settings',
        };
    }
}
