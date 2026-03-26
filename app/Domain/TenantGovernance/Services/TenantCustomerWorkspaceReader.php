<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\HtmlString;

final class TenantCustomerWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceReader $workspaceReader,
        private readonly CustomerProfileTimelineBuilder $timelineBuilder,
    ) {}

    /**
     * @return array<string, string>
     */
    public function teamOptions(Tenant $tenant): array
    {
        $userIds = TenantUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->pluck('user_id')
            ->push((string) $tenant->user_id)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function locationOptions(Tenant $tenant): array
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    public function workspaceHtml(?CustomerProfile $profile): HtmlString|string
    {
        if (! $profile instanceof CustomerProfile) {
            return 'Abre una ficha real para ver el workspace consolidado del cliente.';
        }

        $cards = $this->workspaceReader->workspaceCards($profile);

        $items = collect($cards)
            ->map(fn (array $card): string => '<div style="border:1px solid rgba(15,23,42,.08); border-radius:1rem; padding:1rem; background:#fff;">'
                .'<p style="margin:0; color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.08em;">'.$card['label'].'</p>'
                .'<p style="margin:.35rem 0 0; font-size:1.35rem; font-weight:700; color:#0f172a;">'.$card['value'].'</p>'
                .'<p style="margin:.5rem 0 0; color:#475569; font-size:.92rem;">'.$card['copy'].'</p>'
                .'</div>')
            ->implode('');

        return new HtmlString('<div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));">'.$items.'</div>');
    }

    public function timelineHtml(?CustomerProfile $profile): HtmlString|string
    {
        if (! $profile instanceof CustomerProfile) {
            return 'Todavia no hay timeline disponible.';
        }

        $items = $this->timelineBuilder->build($profile);

        if ($items === []) {
            return 'Aun no hay eventos suficientes para armar la historia operativa de este cliente.';
        }

        $html = collect($items)
            ->map(function (array $item): string {
                $channel = $item['channel'] !== null ? ' / '.$item['channel'] : '';

                return '<div style="display:grid; grid-template-columns:auto 1fr; gap:.85rem; align-items:flex-start; padding:1rem 0; border-bottom:1px solid rgba(15,23,42,.08);">'
                    .'<span style="display:inline-flex; width:.8rem; height:.8rem; border-radius:999px; margin-top:.4rem; background:'
                    .match ($item['color']) {
                        'danger' => '#dc2626',
                        'warning' => '#d97706',
                        'success' => '#059669',
                        'info', 'primary' => '#2563eb',
                        default => '#64748b',
                    }
                    .';"></span>'
                    .'<div>'
                    .'<div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">'
                    .'<p style="margin:0; font-weight:700; color:#0f172a;">'.$item['title'].'</p>'
                    .'<p style="margin:0; color:#64748b; font-size:.9rem;">'.$item['when'].$channel.'</p>'
                    .'</div>'
                    .'<p style="margin:.35rem 0 0; color:#475569;">'.$item['copy'].'</p>'
                    .'</div>'
                    .'</div>';
            })
            ->implode('');

        return new HtmlString('<div style="display:grid;">'.$html.'</div>');
    }
}
