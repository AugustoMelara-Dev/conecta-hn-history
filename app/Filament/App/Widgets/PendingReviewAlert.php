<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PendingReviewAlert extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.pending-review-alert';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return -10;
    }

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        $status = (string) ($tenant->getAttribute('status') ?? '');
        $isActive = (bool) ($tenant->getAttribute('is_active') ?? false);

        return in_array($status, ['pending', 'rejected'], true) || ! $isActive;
    }
}
