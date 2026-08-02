const stateLabel = {
    preview: "Preview",
    simulation: "Sandbox simulation",
    pending_evaluation: "Evaluation pending",
    pending_external_verification: "External verification pending",
    live_evidence: "Live evidence verified",
};

export default function WorkforceRoleCard({ role, selected = false, onSelect }) {
    const label = stateLabel[role.state] ?? "Preview";

    return (
        <article className={`rounded-xl border p-4 ${selected ? "border-primary bg-primary/5" : "border-border bg-card"}`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-foreground">{role.title}</h3>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">{role.objective}</p>
                </div>
                <span className="shrink-0 rounded-full border border-border px-2 py-1 text-[11px] font-semibold text-muted-foreground">
                    {label}
                </span>
            </div>
            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Risk</dt>
                    <dd className="mt-1 capitalize text-foreground">{role.risk_profile}</dd>
                </div>
                <div>
                    <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Evidence</dt>
                    <dd className="mt-1 text-foreground">{role.live_evidence?.verified ? "Verified" : "Pending"}</dd>
                </div>
            </dl>
            <button
                type="button"
                onClick={() => onSelect?.(role.key)}
                aria-pressed={selected}
                className="mt-4 rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                {selected ? "Role selected" : "Choose role"}
            </button>
        </article>
    );
}
