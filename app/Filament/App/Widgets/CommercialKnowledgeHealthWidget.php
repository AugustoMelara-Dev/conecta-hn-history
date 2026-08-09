<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Application\Knowledge\Contracts\CommercialKnowledgeHealthReader;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

final class CommercialKnowledgeHealthWidget extends Widget
{
    protected string $view = 'filament.app.widgets.commercial-knowledge-health-widget';

    protected int|string|array $columnSpan = 'full';

    public int $score = 0;

    public bool $publiclyResolvable = false;

    /** @var list<array{code:string,label:string,action_url:string,severity:string}> */
    public array $issues = [];

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        return $tenant instanceof Tenant && $user instanceof User && $user->canAccessTenant($tenant);
    }

    public static function getSort(): int
    {
        return 2;
    }

    public function mount(CommercialKnowledgeHealthReader $reader): void
    {
        abort_unless(self::canView(), 403);
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Tenant, 404);
        $health = $reader->read((string) $tenant->getKey());
        $this->score = $health->score;
        $this->publiclyResolvable = $health->publiclyResolvable;
        $this->issues = $health->issues;
    }
}
