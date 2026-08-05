const stateCopy = {
    degraded: "Some sources are temporarily unavailable",
    forbidden: "You do not have permission to view this cockpit",
    loading: "Loading operational cockpit",
};

const formatMoney = (minor, currency) =>
    new Intl.NumberFormat("en-US", {
        style: "currency",
        currency,
    }).format((minor ?? 0) / 100);

export default function OperationalCockpit({ snapshot = {}, status = "loading" }) {
    if (status === "loading" || status === "forbidden") {
        return (
            <section aria-live="polite" aria-busy={status === "loading"}>
                <p className="biz-muted">{stateCopy[status]}</p>
            </section>
        );
    }

    const isEmpty =
        status === "empty" ||
        ((snapshot.attentionRequired ?? 0) === 0 &&
            (snapshot.activeOpportunities ?? 0) === 0 &&
            (snapshot.upcomingOrders ?? 0) === 0 &&
            (snapshot.upcomingReservations ?? 0) === 0 &&
            (snapshot.healthIssues ?? []).length === 0);

    return (
        <section className="biz-stack" aria-labelledby="operational-cockpit-title">
            <div className="biz-split">
                <div>
                    <p className="biz-kicker">Today&apos;s work</p>
                    <h2 id="operational-cockpit-title" className="biz-heading">
                        Operational cockpit
                    </h2>
                    <p className="biz-muted">
                        Deterministic queues from your canonical business records.
                    </p>
                </div>
                <a className="biz-button" href={snapshot.nextActionUrl}>
                    {snapshot.nextActionLabel}
                </a>
            </div>

            {status === "degraded" && (
                <p className="biz-card-warning" role="status">
                    {stateCopy.degraded}
                </p>
            )}

            {isEmpty ? (
                <div className="biz-stat">
                    <p className="biz-action-title">Operations are clear</p>
                    <p className="biz-muted">No urgent work is waiting in the current snapshot.</p>
                </div>
            ) : (
                <>
                    <div className="biz-metrics" aria-label="Operational work queues">
                        <Metric label="Needs attention" value={snapshot.attentionRequired ?? 0} />
                        <Metric label="Active opportunities" value={snapshot.activeOpportunities ?? 0} />
                        <Metric label="Aging opportunities" value={snapshot.agingOpportunities ?? 0} />
                        <Metric label="Upcoming orders" value={snapshot.upcomingOrders ?? 0} />
                        <Metric label="Upcoming reservations" value={snapshot.upcomingReservations ?? 0} />
                        <Metric
                            label={`${snapshot.overdueQuotes ?? 0} overdue ${
                                (snapshot.overdueQuotes ?? 0) === 1 ? "quote" : "quotes"
                            }`}
                            value={snapshot.overdueQuotes ?? 0}
                        />
                    </div>

                    {(snapshot.healthIssues ?? []).length > 0 && (
                        <div className="biz-list" aria-label="Knowledge health issues">
                            {snapshot.healthIssues.map((issue) => (
                                <a
                                    className="biz-action"
                                    href={issue.remediationUrl}
                                    key={`${issue.code}:${issue.evidenceId}`}
                                >
                                    <span className="biz-action-title">
                                        {issue.evidenceLabel}: {issue.message}
                                    </span>
                                    <span className="biz-muted">{issue.severity}</span>
                                </a>
                            ))}
                        </div>
                    )}
                </>
            )}

            <div className="biz-metrics" aria-label="Verified outcomes and AI allowance">
                <Metric
                    label="Verified outcomes today"
                    value={formatMoney(snapshot.verifiedOutcomeMinor, snapshot.currency)}
                />
                <Metric label="AI allowance" value={snapshot.aiAllowance ?? 0} />
                <Metric label="AI cost today" value={snapshot.aiCostToday ?? 0} />
            </div>
        </section>
    );
}

function Metric({ label, value }) {
    return (
        <div className="biz-stat">
            <p className="biz-stat-label">{label}</p>
            <p className="biz-stat-value">{value}</p>
        </div>
    );
}
