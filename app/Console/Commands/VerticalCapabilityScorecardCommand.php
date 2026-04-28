<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\TenantGovernance\VerticalCapabilityScorecard;
use App\Models\Announcement;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class VerticalCapabilityScorecardCommand extends Command
{
    protected $signature = 'tenant:vertical-scorecard {--json : Output raw JSON scorecard}';

    protected $description = 'Generate capability scorecard by business vertical (ready|partial|missing).';

    public function __construct(
        private readonly VerticalCapabilityScorecard $verticalCapabilityScorecard,
    ) {
        parent::__construct();
    }

    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_MODULES = [
        'retail' => ['catalog', 'orders', 'marketing', 'contact'],
        'food' => ['catalog', 'orders', 'marketing', 'schedule'],
        'service' => ['services', 'appointments', 'staff', 'contact'],
        'hybrid' => ['catalog', 'services', 'orders', 'appointments', 'contact'],
    ];

    public function handle(): int
    {
        $rows = Tenant::query()
            ->select(['id', 'name', 'slug', 'business_type'])
            ->get()
            ->map(fn (Tenant $tenant): array => $this->buildTenantScore($tenant))
            ->values();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'verticals' => $this->verticalCapabilityScorecard->byBusinessType(),
                'tenants' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $summary = $this->summarizeByVertical($rows);

        $this->table(
            ['vertical', 'ready', 'partial', 'missing', 'total'],
            $summary->map(static fn (array $item): array => [
                $item['vertical'],
                $item['ready'],
                $item['partial'],
                $item['missing'],
                $item['total'],
            ])->all()
        );

        $this->newLine();
        $this->info('Backlog sugerido por vertical (impacto activación D+7):');

        foreach ($rows as $row) {
            if ($row['status'] === 'ready') {
                continue;
            }

            $missingModules = collect($row['modules'])
                ->filter(static fn (bool $isReady): bool => $isReady === false)
                ->keys()
                ->values()
                ->implode(', ');

            $this->line("- {$row['slug']} ({$row['vertical']}): {$missingModules}");
        }

        $this->newLine();
        $this->info('Readiness RubroManifest (billing | operations | marketing | loyalty | ai_content | analytics | experience):');

        foreach ($this->verticalCapabilityScorecard->byBusinessType() as $businessType => $vertical) {
            $readiness = $vertical['readiness'];
            $this->line(sprintf(
                '- %s: billing=%s, operations=%s, marketing=%s, loyalty=%s, ai_content=%s, analytics=%s, experience=%s',
                $businessType,
                $readiness['billing']['status'],
                $readiness['operations']['status'],
                $readiness['marketing']['status'],
                $readiness['loyalty']['status'],
                $readiness['ai_content']['status'],
                $readiness['analytics']['status'],
                $readiness['experience']['status'],
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   slug: string,
     *   vertical: string,
     *   status: string,
     *   score: int,
     *   modules: array<string, bool>
     * }
     */
    private function buildTenantScore(Tenant $tenant): array
    {
        $vertical = (string) ($tenant->getRawOriginal('business_type') ?? 'retail');
        $requiredModules = self::REQUIRED_MODULES[$vertical] ?? self::REQUIRED_MODULES['retail'];

        $tenantId = (string) $tenant->getKey();

        $modules = [
            'catalog' => $this->hasRows(Product::class, $tenantId),
            'services' => $this->hasRows(Service::class, $tenantId),
            'orders' => $this->hasRows(Order::class, $tenantId),
            'appointments' => $this->hasRows(Appointment::class, $tenantId),
            'staff' => $this->hasRows(ServiceProvider::class, $tenantId),
            'marketing' => $this->hasRows(Coupon::class, $tenantId)
                || $this->hasRows(Campaign::class, $tenantId)
                || $this->hasRows(Announcement::class, $tenantId),
            'schedule' => $this->hasRows(Appointment::class, $tenantId) || $tenant->openingHours()->exists(),
            'contact' => filled($tenant->whatsapp_number) || filled($tenant->phone),
        ];

        $requiredChecks = collect($requiredModules)->mapWithKeys(
            static fn (string $module): array => [$module => (bool) ($modules[$module] ?? false)]
        );

        $completed = $requiredChecks->filter()->count();
        $total = max(1, $requiredChecks->count());
        $score = (int) round(($completed / $total) * 100);
        $status = $this->statusFromScore($score);

        return [
            'tenant_id' => (string) $tenant->getKey(),
            'slug' => (string) $tenant->slug,
            'vertical' => $vertical,
            'status' => $status,
            'score' => $score,
            'modules' => $requiredChecks->all(),
        ];
    }

    private function statusFromScore(int $score): string
    {
        if ($score >= 90) {
            return 'ready';
        }

        if ($score >= 40) {
            return 'partial';
        }

        return 'missing';
    }

    /**
     * @param  Collection<int, array{
     *   tenant_id:string,
     *   slug:string,
     *   vertical:string,
     *   status:string,
     *   score:int,
     *   modules:array<string, bool>
     * }>  $rows
     * @return Collection<int, array{
     *   vertical:string,
     *   ready:int,
     *   partial:int,
     *   missing:int,
     *   total:int
     * }>
     */
    private function summarizeByVertical(Collection $rows): Collection
    {
        return $rows
            ->groupBy('vertical')
            ->map(function (Collection $group, string $vertical): array {
                $ready = $group->where('status', 'ready')->count();
                $partial = $group->where('status', 'partial')->count();
                $missing = $group->where('status', 'missing')->count();

                return [
                    'vertical' => $vertical,
                    'ready' => $ready,
                    'partial' => $partial,
                    'missing' => $missing,
                    'total' => $group->count(),
                ];
            })
            ->values();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function hasRows(string $modelClass, string $tenantId): bool
    {
        return $modelClass::query()->where('tenant_id', $tenantId)->exists();
    }
}
