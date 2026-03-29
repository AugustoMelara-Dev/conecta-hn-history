<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileWorkspaceHtmlReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceStateReader $workspaceStateReader,
        private readonly CustomerProfileWorkspaceCardsHtmlReader $cardsHtmlReader,
    ) {}

    public function render(?CustomerProfile $profile): HtmlString|string
    {
        $state = $this->workspaceStateReader->read($profile);
        $cards = $state['cards'];

        if ($cards === []) {
            return $state['empty'];
        }

        return $this->cardsHtmlReader->render($cards);
    }
}
