<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Pages;

use App\Filament\App\Widgets\OnboardingActivationWidget;
use Filament\Resources\Pages\ListRecords;

abstract class ListTenantRecords extends ListRecords
{
    public function getTitle(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->shouldShowOnboardingWidget()) {
            return [];
        }

        if (! class_exists(OnboardingActivationWidget::class)) {
            return [];
        }

        request()->attributes->set('onboarding_module', $this->onboardingModuleContext());

        return [
            OnboardingActivationWidget::class,
        ];
    }

    protected function shouldShowOnboardingWidget(): bool
    {
        return true;
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
