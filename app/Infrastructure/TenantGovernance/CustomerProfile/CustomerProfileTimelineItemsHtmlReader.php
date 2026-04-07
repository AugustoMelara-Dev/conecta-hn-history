<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use Illuminate\Support\HtmlString;

final class CustomerProfileTimelineItemsHtmlReader
{
    /**
     * @param  list<array{type:string,title:string,copy:string,channel:?string,when:string,color:string}>  $items
     */
    public function render(array $items): HtmlString
    {
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
