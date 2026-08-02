<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Application\Ai\Presentation\AiMarketPresenter;
use App\Application\Markets\MarketContext;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Scopes\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EloquentTenantAgentReader
{
    public function __construct(
        private readonly KnowledgeRetriever $knowledge,
        private readonly TenantMarketResolver $markets,
        private readonly AiMarketPresenter $presenter,
    ) {}

    /** @return array<string, mixed> */
    public function answer(string $tenantId, string $message): array
    {
        // INFRA_TENANT_FILTER: public location preference is not private-tenant authority; exact ID remains mandatory.
        $tenant = Tenant::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereKey($tenantId)
            ->firstOrFail();
        $market = $this->markets->resolve($tenant);
        $intent = $this->intent($message);

        return match ($intent) {
            'sales_today' => $this->salesToday($tenantId, $market),
            'appointments_today' => $this->appointmentsToday($tenantId, $market),
            'catalog', 'availability' => $this->catalog($tenantId, $market, $intent, $message),
            default => $this->unknown($tenantId, $market),
        };
    }

    private function intent(string $message): string
    {
        $normalized = Str::ascii(Str::lower($message));

        if (str_contains($normalized, 'vendi') || str_contains($normalized, 'vendido')
            || str_contains($normalized, 'ventas') || str_contains($normalized, 'sell')
            || str_contains($normalized, 'sales')) {
            return 'sales_today';
        }

        if (str_contains($normalized, 'reserv') || str_contains($normalized, 'cita')
            || str_contains($normalized, 'agenda') || str_contains($normalized, 'appointment')) {
            return 'appointments_today';
        }

        if (str_contains($normalized, 'disponib') || str_contains($normalized, 'stock') || str_contains($normalized, 'existencia')) {
            return 'availability';
        }

        if (str_contains($normalized, 'catalog') || str_contains($normalized, 'producto') || str_contains($normalized, 'servicio')) {
            return 'catalog';
        }

        return 'unknown';
    }

    /** @return array<string, mixed> */
    private function salesToday(string $tenantId, MarketContext $market): array
    {
        [$utcStart, $utcEnd] = $this->localDayUtcWindow($market);
        $ordersCount = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $utcStart)
            ->where('created_at', '<', $utcEnd)
            ->where('status', '!=', 'cancelled')
            ->count();
        $verifiedNetRevenueMinor = (int) DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->where('aggregate_type', 'order')
            ->whereIn('outcome', [
                CommercialOutcome::OrderPaid->value,
                CommercialOutcome::OrderRefunded->value,
            ])
            ->where('currency', $market->currencyCode)
            ->where('source_occurred_at', '>=', $utcStart)
            ->where('source_occurred_at', '<', $utcEnd)
            ->sum('amount_minor');
        $verifiedNetRevenue = $verifiedNetRevenueMinor / 100;
        $evidenceId = 'sales-summary:'.$this->presenter->localDate($market)->toDateString();

        return $this->response(
            tenantId: $tenantId,
            market: $market,
            answer: 'Hoy tenes '.$ordersCount.' ordenes registradas e ingresos netos verificados por '
                .$this->presenter->money($verifiedNetRevenueMinor, $market).'.',
            data: [
                'intent' => 'sales_today',
                'orders_count' => $ordersCount,
                'verified_net_revenue' => $verifiedNetRevenue,
                'verified_net_revenue_minor' => $verifiedNetRevenueMinor,
                'currency' => $market->currencyCode,
            ],
            grounding: [[
                'type' => 'sales_summary',
                'id' => $evidenceId,
                'orders_count' => $ordersCount,
                'verified_net_revenue' => $verifiedNetRevenue,
                'verified_net_revenue_minor' => $verifiedNetRevenueMinor,
                'currency' => $market->currencyCode,
            ]],
            tool: 'tenant_sales.read',
        );
    }

    /** @return array<string, mixed> */
    private function appointmentsToday(string $tenantId, MarketContext $market): array
    {
        [$utcStart, $utcEnd] = $this->localDayUtcWindow($market);
        $appointments = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('scheduled_at', '>=', $utcStart)
            ->where('scheduled_at', '<', $utcEnd)
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get(['id', 'scheduled_at', 'end_at', 'status']);
        $items = $appointments->map(static fn (object $appointment): array => [
            'id' => $appointment->id,
            'scheduled_at' => CarbonImmutable::parse((string) $appointment->scheduled_at, 'UTC')
                ->setTimezone($market->timezone)->toIso8601String(),
            'end_at' => CarbonImmutable::parse((string) $appointment->end_at, 'UTC')
                ->setTimezone($market->timezone)->toIso8601String(),
            'status' => (string) $appointment->status,
        ])->values()->all();
        $grounding = $appointments->map(static fn (object $appointment): array => [
            'type' => 'appointment',
            'id' => 'appointment:'.$appointment->id,
            'scheduled_at' => CarbonImmutable::parse((string) $appointment->scheduled_at, 'UTC')
                ->setTimezone($market->timezone)->toIso8601String(),
            'end_at' => CarbonImmutable::parse((string) $appointment->end_at, 'UTC')
                ->setTimezone($market->timezone)->toIso8601String(),
            'status' => (string) $appointment->status,
        ])->values()->all();

        return $this->response(
            tenantId: $tenantId,
            market: $market,
            answer: $items === []
                ? 'Hoy no hay reservas registradas para este negocio.'
                : 'Hoy hay '.count($items).' reservas registradas para este negocio.',
            data: [
                'intent' => 'appointments_today',
                'appointments_count' => count($items),
                'appointments' => $items,
            ],
            grounding: $grounding,
            tool: 'tenant_appointments.read',
        );
    }

    /** @return array<string, mixed> */
    private function catalog(string $tenantId, MarketContext $market, string $intent, string $message): array
    {
        $candidates = $this->knowledge->searchTenant($message, $tenantId);
        $hasCandidates = $candidates !== [];
        $productIds = $this->candidateIds($candidates, 'product');
        $serviceIds = $this->candidateIds($candidates, 'service');
        $products = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->when($hasCandidates, fn (Builder $query): Builder => $query->whereIn('id', $productIds))
            ->when(Schema::hasColumn('products', 'is_active'), fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(function (Product $product) use ($market): array {
                $availability = ! (bool) ($product->manage_stock ?? false) || (int) ($product->stock_quantity ?? 0) > 0
                    ? 'available'
                    : ((bool) ($product->allow_backorders ?? false) ? 'backorder' : 'unavailable');

                return [
                    'type' => 'product',
                    'id' => 'product:'.$product->getKey(),
                    'name' => mb_substr(strip_tags((string) $product->name), 0, 100),
                    'description' => mb_substr(strip_tags((string) ($product->description ?? '')), 0, 120),
                    'price' => (float) $product->price,
                    'price_label' => $this->presenter->money($this->priceMinor($product, $market), $market),
                    'availability' => $availability,
                ];
            });
        $services = Service::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->when($hasCandidates, fn (Builder $query): Builder => $query->whereIn('id', $serviceIds))
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (Service $service): array => [
                'type' => 'service',
                'id' => 'service:'.$service->getKey(),
                'name' => mb_substr(strip_tags((string) $service->name), 0, 100),
                'description' => mb_substr(strip_tags((string) ($service->description ?? '')), 0, 120),
                'price' => (float) $service->price,
                'price_label' => $this->presenter->money($this->priceMinor($service, $market), $market),
                'availability' => 'available',
            ]);
        $items = collect($products->all())->merge($services->all())->take(20)->values()->all();

        return $this->response(
            tenantId: $tenantId,
            market: $market,
            answer: $items === []
                ? 'No hay productos o servicios activos para esta consulta.'
                : 'Encontre '.count($items).' opciones activas en el catalogo de este negocio.',
            data: ['intent' => $intent, 'items' => $items, 'currency' => $market->currencyCode],
            grounding: $items,
            tool: $intent === 'availability' ? 'tenant_availability.read' : 'tenant_catalog.read',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return list<string>
     */
    private function candidateIds(array $candidates, string $type): array
    {
        return collect($candidates)
            ->filter(static fn (array $candidate): bool => ($candidate['resource_type'] ?? null) === $type
                && is_string($candidate['resource_id'] ?? null)
                && ctype_digit($candidate['resource_id']))
            ->pluck('resource_id')
            ->unique()
            ->values()
            ->all();
    }

    private function unknown(string $tenantId, MarketContext $market): array
    {
        return $this->response(
            tenantId: $tenantId,
            market: $market,
            answer: 'Puedo ayudarte con ventas verificadas de hoy, citas de hoy, catalogo y disponibilidad de este negocio.',
            data: ['intent' => 'unknown'],
            grounding: [],
            tool: null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $grounding
     * @return array<string, mixed>
     */
    private function response(
        string $tenantId,
        MarketContext $market,
        string $answer,
        array $data,
        array $grounding,
        ?string $tool,
    ): array {
        return [
            'answer' => $answer,
            'source' => 'private_tenant_scope',
            'tenant_id' => $tenantId,
            'data' => $data,
            'fallback' => true,
            'grounding' => $grounding,
            'tool' => $tool,
            'meta' => [
                'mode' => 'deterministic_tenant_private_agent',
                'llm_used' => false,
                'privacy_scope' => 'tenant_private_only',
                'tenant_id' => $tenantId,
                'country' => $market->countryCode,
                'locale' => $market->localeCode,
                'currency' => $market->currencyCode,
                'timezone' => $market->timezone,
            ],
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function localDayUtcWindow(MarketContext $market): array
    {
        $localStart = $this->presenter->localDate($market)->startOfDay();

        return [$localStart->utc(), $localStart->addDay()->utc()];
    }

    private function priceMinor(Product|Service $item, MarketContext $market): int
    {
        $declaredCurrency = strtoupper((string) ($item->currency ?? ''));
        if ($declaredCurrency !== '' && $declaredCurrency !== $market->currencyCode) {
            throw new \InvalidArgumentException('AI catalog currency is invalid.');
        }

        return $item->price_minor === null
            ? (int) round(((float) $item->price) * 100)
            : (int) $item->price_minor;
    }
}
