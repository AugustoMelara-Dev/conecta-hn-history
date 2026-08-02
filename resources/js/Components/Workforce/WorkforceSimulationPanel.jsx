export default function WorkforceSimulationPanel({ simulation = null, liveEvidence = {} }) {
    const isSimulation = Boolean(simulation?.enabled);

    return (
        <section aria-labelledby="workforce-simulation-title" className="rounded-xl border border-border bg-muted/30 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 id="workforce-simulation-title" className="font-semibold text-foreground">Sandbox simulation</h3>
                <span className="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                    {isSimulation ? "Simulation only" : "No simulation selected"}
                </span>
            </div>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {simulation?.provider_label || "No simulation selected yet. Choose a sandbox case before requesting activation."}
            </p>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="font-medium text-foreground">Evidence source</dt>
                    <dd className="mt-1 text-muted-foreground">{simulation?.evidence_label || "Scenario source"}</dd>
                </div>
                <div>
                    <dt className="font-medium text-foreground">External delivery</dt>
                    <dd className="mt-1 text-muted-foreground">
                        {liveEvidence.verified ? "Evidence verified" : "External verification is pending"}
                    </dd>
                </div>
            </dl>
            {!liveEvidence.verified && (
                <p role="status" className="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-foreground">
                    External verification is pending. This preview does not claim that a provider or channel is live.
                </p>
            )}
        </section>
    );
}
