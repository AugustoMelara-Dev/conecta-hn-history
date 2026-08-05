<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Application\Operations\OperationalCockpit;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Auth\Access\AuthorizationException;

final class OperationalCockpitWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.operational-cockpit-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $snapshot = [];

    public string $status = 'loading';

    public static function getSort(): int
    {
        return 0;
    }

    public function mount(OperationalCockpit $cockpit): void
    {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if ($tenant === null || $actor === null) {
            $this->status = 'forbidden';

            return;
        }

        try {
            $snapshot = $cockpit->snapshot(
                (string) $tenant->getKey(),
                (string) $actor->getKey(),
            );
            $this->snapshot = [
                'currency' => $snapshot->currency,
                'verifiedOutcomeMinor' => $snapshot->verifiedOutcomeMinor,
                'attentionRequired' => $snapshot->attentionRequired,
                'activeOpportunities' => $snapshot->activeOpportunities,
                'agingOpportunities' => $snapshot->agingOpportunities,
                'upcomingOrders' => $snapshot->upcomingOrders,
                'upcomingReservations' => $snapshot->upcomingReservations,
                'overdueQuotes' => $snapshot->overdueQuotes,
                'failedImports' => $snapshot->failedImports,
                'failedAutomations' => $snapshot->failedAutomations,
                'aiAllowance' => $snapshot->aiAllowance,
                'aiCostToday' => $snapshot->aiCostToday,
                'healthIssues' => array_map(
                    static fn ($issue): array => [
                        'code' => $issue->code,
                        'severity' => $issue->severity,
                        'evidenceId' => $issue->evidenceId,
                        'evidenceLabel' => $issue->evidenceLabel,
                        'message' => $issue->message,
                        'remediationUrl' => $issue->remediationUrl,
                    ],
                    $snapshot->healthIssues,
                ),
                'nextAction' => $snapshot->nextAction,
                'nextActionLabel' => $snapshot->nextActionLabel,
                'nextActionUrl' => $snapshot->nextActionUrl,
                'degradedSources' => $snapshot->degradedSources,
            ];
            $this->status = $snapshot->degradedSources !== []
                ? 'degraded'
                : ($this->isEmpty($this->snapshot) ? 'empty' : 'ready');
        } catch (AuthorizationException) {
            $this->status = 'forbidden';
        } catch (\Throwable $exception) {
            report($exception);
            $this->snapshot = [
                'currency' => 'USD',
                'verifiedOutcomeMinor' => 0,
                'attentionRequired' => 0,
                'activeOpportunities' => 0,
                'agingOpportunities' => 0,
                'upcomingOrders' => 0,
                'upcomingReservations' => 0,
                'overdueQuotes' => 0,
                'failedImports' => 0,
                'failedAutomations' => 0,
                'aiAllowance' => 0,
                'aiCostToday' => 0,
                'healthIssues' => [],
                'nextAction' => 'retry_cockpit',
                'nextActionLabel' => 'Retry operational cockpit',
                'nextActionUrl' => request()->url(),
                'degradedSources' => ['cockpit'],
            ];
            $this->status = 'degraded';
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function isEmpty(array $snapshot): bool
    {
        return (int) $snapshot['attentionRequired'] === 0
            && (int) $snapshot['activeOpportunities'] === 0
            && (int) $snapshot['upcomingOrders'] === 0
            && (int) $snapshot['upcomingReservations'] === 0
            && $snapshot['healthIssues'] === [];
    }
}
