<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\TenantGovernance\SuperAdmin\SuperAdminTenantPlanReader;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

final class Billing extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Facturación';

    protected static ?string $title = 'Facturación de la plataforma';

    protected static ?string $slug = 'billing';

    protected static ?int $navigationSort = 100;

    protected static string|\UnitEnum|null $navigationGroup = 'Finanzas';

    protected string $view = 'filament.pages.billing';

    public int $pendingPayments = 0;

    public int $approvedPayments = 0;

    public int $rejectedPayments = 0;

    public int $proBusinesses = 0;

    public int $baseBusinesses = 0;

    public string $proPlanPrice = 'L. 99.00';

    public string $supportEmail = 'soporte@conectahn.com';

    public string $supportWhatsApp = '+504 0000-0000';

    /**
     * @var array<int, array<string, string|null>>
     */
    public array $recentPayments = [];

    public int $businessesWithCatalog = 0;

    public int $businessesWithPayments = 0;

    public float $proConversionRate = 0;

    /** @var list<array{name: string, total: int, pro: int, conversion: string}> */
    public array $cityFunnel = [];

    /** @var list<array{name: string, total: int, pro: int, conversion: string}> */
    public array $categoryFunnel = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);
        $role = $user->role;
        $hasSuperAdminRole = $role instanceof UserRole
            ? $role === UserRole::SUPER_ADMIN
            : (string) $role === UserRole::SUPER_ADMIN->value;

        return $hasSuperAdminRole || $user->isSuperAdmin() || $isWhitelisted;
    }

    public function mount(): void
    {
        $this->loadSettings();
        $this->loadMetrics();
    }

    private function loadSettings(): void
    {
        try {
            $settings = app(GeneralSettings::class);

            $this->proPlanPrice = 'L. ' . number_format((float) ($settings->pro_plan_price ?? 99), 2);
            $this->supportEmail = (string) ($settings->support_email ?? $this->supportEmail);

            $supportWhatsApp = (string) ($settings->support_whatsapp_number ?? '');
            if ($supportWhatsApp !== '') {
                $this->supportWhatsApp = $supportWhatsApp;
            }
        } catch (\Throwable) {
            // Keep the page usable with defaults.
        }
    }

    private function loadMetrics(): void
    {
        try {
            $payments = SubscriptionPayment::query()->withoutGlobalScopes();

            $this->pendingPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->count();

            $this->approvedPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_APPROVED)
                ->count();

            $this->rejectedPayments = (clone $payments)
                ->where('status', SubscriptionPayment::STATUS_REJECTED)
                ->count();

            $tenantSnapshot = Tenant::query()
                ->with([
                    'city:id,name',
                    'category:id,name',
                ])
                ->withCount([
                    'products as products_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
                ])
                ->get();

            $this->proBusinesses = $tenantSnapshot
                ->filter(fn (Tenant $tenant): bool => $this->planReader()->isPaidPlan($tenant))
                ->count();

            $this->baseBusinesses = $tenantSnapshot
                ->filter(fn (Tenant $tenant): bool => $this->planReader()->isBasePlan($tenant))
                ->count();

            $this->recentPayments = (clone $payments)
                ->with('tenant')
                ->latest()
                ->limit(6)
                ->get()
                ->map(function (SubscriptionPayment $payment): array {
                    $tenant = $payment->tenant()->first();

                    return [
                        'business' => $tenant instanceof Tenant ? (string) $tenant->getAttribute('name') : 'Negocio sin registro',
                        'reference' => (string) ($payment->reference_number ?? 'Sin referencia'),
                        'status' => match ((string) $payment->status) {
                            SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                            SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                            SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                            default => 'Desconocido',
                        },
                        'amount' => 'L. ' . number_format((float) $payment->amount, 2),
                        'reported_at' => optional($payment->created_at)?->format('d/m/Y H:i'),
                    ];
                })
                ->all();

            $this->businessesWithCatalog = $tenantSnapshot
                ->where('products_total', '>', 0)
                ->count();

            $this->businessesWithPayments = SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->distinct('tenant_id')
                ->count('tenant_id');

            $totalBusinesses = max(1, $tenantSnapshot->count());
            $this->proConversionRate = round(($this->proBusinesses / $totalBusinesses) * 100, 1);

            $this->cityFunnel = $tenantSnapshot
                ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->city?->name ?? 'Sin ciudad'))
                ->map(function ($group, string $name): array {
                    $total = $group->count();
                    $pro = $group->filter(fn (Tenant $tenant): bool => $this->planReader()->isPaidPlan($tenant))->count();

                    return [
                        'name' => $name,
                        'total' => $total,
                        'pro' => $pro,
                        'conversion' => number_format(($pro / max(1, $total)) * 100, 1).'%',
                    ];
                })
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->all();

            $this->categoryFunnel = $tenantSnapshot
                ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->category?->name ?? 'Sin categoría'))
                ->map(function ($group, string $name): array {
                    $total = $group->count();
                    $pro = $group->filter(fn (Tenant $tenant): bool => $this->planReader()->isPaidPlan($tenant))->count();

                    return [
                        'name' => $name,
                        'total' => $total,
                        'pro' => $pro,
                        'conversion' => number_format(($pro / max(1, $total)) * 100, 1).'%',
                    ];
                })
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->all();
        } catch (\Throwable) {
            $this->recentPayments = [];
            $this->businessesWithCatalog = 0;
            $this->businessesWithPayments = 0;
            $this->proConversionRate = 0;
            $this->cityFunnel = [];
            $this->categoryFunnel = [];
        }
    }

    private function planReader(): SuperAdminTenantPlanReader
    {
        return app(SuperAdminTenantPlanReader::class);
    }
}
