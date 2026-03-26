<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileWorkspaceHtmlReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceStateReader $workspaceStateReader,
    ) {}

    public function render(?CustomerProfile $profile): HtmlString|string
    {
        $state = $this->workspaceStateReader->read($profile);
        $cards = $state['cards'];

        if ($cards === []) {
            return $state['empty'];
        }

        $items = collect($cards)
            ->map(fn (array $card): string => '<div style="border:1px solid rgba(15,23,42,.08); border-radius:1rem; padding:1rem; background:#fff;">'
                .'<p style="margin:0; color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.08em;">'.$card['label'].'</p>'
                .'<p style="margin:.35rem 0 0; font-size:1.35rem; font-weight:700; color:#0f172a;">'.$card['value'].'</p>'
                .'<p style="margin:.5rem 0 0; color:#475569; font-size:.92rem;">'.$card['copy'].'</p>'
                .'</div>')
            ->implode('');

        return new HtmlString('<div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));">'.$items.'</div>');
    }
}
