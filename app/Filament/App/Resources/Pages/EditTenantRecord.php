<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

abstract class EditTenantRecord extends EditRecord
{
    public function getTitle(): string
    {
        return 'Editar '.Str::lower($this->getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    protected function getHeaderWidgets(): array
    {
        if (! class_exists(\App\Filament\App\Widgets\OnboardingActivationWidget::class)) {
            return [];
        }

        request()->attributes->set('onboarding_module', $this->onboardingModuleContext());

        return [
            \App\Filament\App\Widgets\OnboardingActivationWidget::class,
        ];
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
