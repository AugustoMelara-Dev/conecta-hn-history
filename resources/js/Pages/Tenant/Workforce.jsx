import { Head } from "@inertiajs/react";
import { useState } from "react";

import WorkforceActivationWizard from "@/Components/Workforce/WorkforceActivationWizard";
import WorkforceRoleCard from "@/Components/Workforce/WorkforceRoleCard";
import WorkforceSimulationPanel from "@/Components/Workforce/WorkforceSimulationPanel";

export default function Workforce({ workforce = {} }) {
    const [selectedRole, setSelectedRole] = useState(workforce.roles?.[0]?.key ?? "");
    const [activation, setActivation] = useState(null);
    const [error, setError] = useState(null);

    const requestActivation = async (payload) => {
        setError(null);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(workforce.activation_url || "/api/v1/tenant/workforce/activate", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
            },
            body: JSON.stringify(payload),
        });
        const body = await response.json();
        if (!response.ok) {
            setError(body.code || "Activation request could not be prepared.");
            return;
        }
        setActivation(body.data);
    };

    return (
        <>
            <Head title="Dovra AI Workforce" />
            <main className="mx-auto max-w-6xl space-y-6 px-4 py-8" aria-labelledby="workforce-page-title">
                <header className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Dovra AI workforce</p>
                    <h1 id="workforce-page-title" className="mt-2 text-3xl font-semibold tracking-tight text-foreground">AI employees by job, governed by evidence</h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">Prepare roles with Business Graph context, conversations, controlled actions, approval and measurable outcomes. A role is not described as live until authoritative evidence is present.</p>
                    <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                        <div className="rounded-lg bg-muted p-3"><dt className="text-muted-foreground">Registry</dt><dd className="mt-1 font-medium text-foreground">{workforce.setup?.registry_status || "pending"}</dd></div>
                        <div className="rounded-lg bg-muted p-3"><dt className="text-muted-foreground">Business Graph facts</dt><dd className="mt-1 font-medium text-foreground">{workforce.setup?.business_graph_facts ?? 0}</dd></div>
                        <div className="rounded-lg bg-muted p-3"><dt className="text-muted-foreground">Channels</dt><dd className="mt-1 font-medium text-foreground">{workforce.channels?.length ?? 0} connected records</dd></div>
                    </dl>
                </header>

                <section aria-labelledby="workforce-roles-title">
                    <h2 id="workforce-roles-title" className="text-xl font-semibold text-foreground">Governed role candidates</h2>
                    {workforce.roles?.length ? <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{workforce.roles.map((role) => <WorkforceRoleCard key={role.key} role={role} selected={selectedRole === role.key} onSelect={setSelectedRole} />)}</div> : <p className="mt-3 rounded-xl border border-border p-4 text-sm text-muted-foreground">No governed role candidate is available. This cockpit remains a preview until the registry supplies one.</p>}
                </section>

                <WorkforceActivationWizard
                    roles={workforce.roles}
                    channels={workforce.channels}
                    selectedRoleKey={selectedRole}
                    onRoleChange={setSelectedRole}
                    onSubmit={requestActivation}
                />
                <WorkforceSimulationPanel simulation={activation?.simulation} liveEvidence={activation?.live_evidence || workforce.live_evidence} />
                {activation && <p role="status" className="rounded-md border border-border bg-muted/30 p-4 text-sm text-foreground">Activation state: <strong>{activation.state}</strong>. This is an evidence state, not a blanket live deployment claim.</p>}
                {error && <p role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">{error}</p>}
            </main>
        </>
    );
}
