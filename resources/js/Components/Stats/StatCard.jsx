/**
 * Components/Stats/StatCard.jsx - Reusable Metric Display
 *
 * FEATURES:
 * - Automatic trend coloring (green up, red down)
 * - Icon slot for customization
 * - Responsive sizing
 *
 * @param {Object} props
 * @param {string} props.title - Metric label
 * @param {string|number} props.value - Main value to display
 * @param {number|null} props.trend - Percentage change (positive/negative/null)
 * @param {string} props.trendLabel - Optional trend context (e.g., "vs last month")
 * @param {React.ReactNode} props.icon - Optional icon element
 * @param {string} props.className - Additional CSS classes
 */

export default function StatCard({
    title,
    value,
    trend = null,
    trendLabel = 'vs período anterior',
    icon = null,
    className = '',
}) {
    const isPositive = trend !== null && trend >= 0;
    const hasTrend = trend !== null;

    return (
        <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 ${className}`}>
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-gray-500 mb-1">{title}</p>
                    <p className="text-2xl font-bold text-gray-900">{value}</p>

                    {hasTrend && (
                        <div className="flex items-center gap-1.5 mt-2">
                            {/* Trend Arrow */}
                            <span className={`flex items-center gap-0.5 text-sm font-medium ${isPositive ? 'text-emerald-600' : 'text-red-600'
                                }`}>
                                {isPositive ? (
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                    </svg>
                                ) : (
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                )}
                                {Math.abs(trend).toFixed(1)}%
                            </span>
                            <span className="text-xs text-gray-400">{trendLabel}</span>
                        </div>
                    )}
                </div>

                {/* Icon */}
                {icon && (
                    <div className={`p-3 rounded-xl ${isPositive || !hasTrend
                            ? 'bg-blue-50 text-blue-600'
                            : 'bg-red-50 text-red-600'
                        }`}>
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );
}
