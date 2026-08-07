<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

/**
 * ReportKey - 25 reports disponibles.
 *
 * Categorías:
 * - Revenue: RevenueByMonth, MrrArr, AvgOrderValue, ProductMargin, DiscountEffectiveness, RefundRate
 * - Customers: CustomerLtv, ChurnRate, CohortRetention, CustomerSegments
 * - Marketing: CampaignRoi, AttributionBySource, ChannelPerformance, NpsScore
 * - Operations: TopProducts, SlowMovers, InventoryTurnover, FulfillmentSla, GeographicDistribution, EmployeePerformance
 * - Sales: ConversionFunnel, DealVelocity, LeadScore, WinRate, ActivityVolume
 */
enum ReportKey: string
{
    // Revenue
    case RevenueByMonth = 'revenue_by_month';
    case MrrArr = 'mrr_arr';
    case AvgOrderValue = 'avg_order_value';
    case ProductMargin = 'product_margin';
    case DiscountEffectiveness = 'discount_effectiveness';
    case RefundRate = 'refund_rate';

    // Customers
    case CustomerLtv = 'customer_ltv';
    case ChurnRate = 'churn_rate';
    case CohortRetention = 'cohort_retention';
    case CustomerSegments = 'customer_segments';

    // Marketing
    case CampaignRoi = 'campaign_roi';
    case AttributionBySource = 'attribution_by_source';
    case ChannelPerformance = 'channel_performance';
    case NpsScore = 'nps_score';

    // Operations
    case TopProducts = 'top_products';
    case SlowMovers = 'slow_movers';
    case InventoryTurnover = 'inventory_turnover';
    case FulfillmentSla = 'fulfillment_sla';
    case GeographicDistribution = 'geographic_distribution';
    case EmployeePerformance = 'employee_performance';

    // Sales
    case ConversionFunnel = 'conversion_funnel';
    case DealVelocity = 'deal_velocity';
    case LeadScore = 'lead_score';
    case WinRate = 'win_rate';
    case ActivityVolume = 'activity_volume';

    public function label(): string
    {
        return match ($this) {
            self::RevenueByMonth => 'Ingresos por mes',
            self::MrrArr => 'MRR / ARR',
            self::AvgOrderValue => 'Ticket promedio',
            self::ProductMargin => 'Margen por producto',
            self::DiscountEffectiveness => 'Efectividad de descuentos',
            self::RefundRate => 'Tasa de reembolso',
            self::CustomerLtv => 'LTV del cliente',
            self::ChurnRate => 'Tasa de churn',
            self::CohortRetention => 'Retención por cohorte',
            self::CustomerSegments => 'Segmentos de clientes',
            self::CampaignRoi => 'ROI de campañas',
            self::AttributionBySource => 'Atribución por fuente',
            self::ChannelPerformance => 'Performance por canal',
            self::NpsScore => 'NPS score',
            self::TopProducts => 'Top productos',
            self::SlowMovers => 'Productos lentos',
            self::InventoryTurnover => 'Rotación de inventario',
            self::FulfillmentSla => 'SLA de fulfillment',
            self::GeographicDistribution => 'Distribución geográfica',
            self::EmployeePerformance => 'Performance del equipo',
            self::ConversionFunnel => 'Embudo de conversión',
            self::DealVelocity => 'Velocidad de deals',
            self::LeadScore => 'Distribución de lead score',
            self::WinRate => 'Win rate',
            self::ActivityVolume => 'Volumen de actividades',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::RevenueByMonth, self::MrrArr, self::AvgOrderValue,
            self::ProductMargin, self::DiscountEffectiveness, self::RefundRate => 'Ingresos',

            self::CustomerLtv, self::ChurnRate, self::CohortRetention,
            self::CustomerSegments => 'Clientes',

            self::CampaignRoi, self::AttributionBySource,
            self::ChannelPerformance, self::NpsScore => 'Marketing',

            self::TopProducts, self::SlowMovers, self::InventoryTurnover,
            self::FulfillmentSla, self::GeographicDistribution,
            self::EmployeePerformance => 'Operaciones',

            self::ConversionFunnel, self::DealVelocity, self::LeadScore,
            self::WinRate, self::ActivityVolume => 'Ventas',
        };
    }

    public function chartType(): string
    {
        return match ($this) {
            self::RevenueByMonth, self::MrrArr => 'line',
            self::AvgOrderValue, self::RefundRate, self::ChurnRate => 'gauge',
            self::ProductMargin, self::DiscountEffectiveness => 'bar',
            self::CustomerLtv, self::CustomerSegments => 'table',
            self::CohortRetention => 'heatmap',
            self::CampaignRoi => 'bar',
            self::AttributionBySource => 'pie',
            self::ChannelPerformance => 'bar',
            self::NpsScore => 'gauge',
            self::TopProducts, self::SlowMovers => 'table',
            self::InventoryTurnover, self::EmployeePerformance => 'bar',
            self::FulfillmentSla => 'gauge',
            self::GeographicDistribution => 'map',
            self::ConversionFunnel => 'funnel',
            self::DealVelocity => 'bar',
            self::LeadScore => 'pie',
            self::WinRate => 'bar',
            self::ActivityVolume => 'line',
        };
    }

    public function icon(): string
    {
        return match ($this->category()) {
            'Ingresos' => 'heroicon-o-banknotes',
            'Clientes' => 'heroicon-o-user-group',
            'Marketing' => 'heroicon-o-megaphone',
            'Operaciones' => 'heroicon-o-cog-6-tooth',
            'Ventas' => 'heroicon-o-chart-bar',
            default => 'heroicon-o-document-chart-bar',
        };
    }

    public function color(): string
    {
        return match ($this->category()) {
            'Ingresos' => 'success',
            'Clientes' => 'info',
            'Marketing' => 'warning',
            'Operaciones' => 'gray',
            'Ventas' => 'primary',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
