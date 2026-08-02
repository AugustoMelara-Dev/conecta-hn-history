import { useId, useState } from "react";

const steps = ["Describe", "Configure", "Connect", "Simulate", "Publish"];
const outcomes = [
    ["lead_qualification", "Lead qualification"],
    ["quote", "Quote"],
    ["reservation", "Reservation"],
    ["support_resolution", "Support resolution"],
    ["task_completion", "Task completion"],
];

export default function WorkforceActivationWizard({ roles = [], channels = [], onSubmit }) {
    const [activeStep, setActiveStep] = useState(0);
    const [roleKey, setRoleKey] = useState(roles[0]?.key ?? "");
    const [outcome, setOutcome] = useState(outcomes[0][0]);
    const [selectedChannels, setSelectedChannels] = useState([]);
    const [approvalMode, setApprovalMode] = useState("human_review");
    const id = useId();

    const toggleChannel = (key) => setSelectedChannels((current) => (
        current.includes(key) ? current.filter((item) => item !== key) : [...current, key]
    ));

    return (
        <section aria-labelledby="workforce-wizard-title" className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Activation workflow</p>
            <h2 id="workforce-wizard-title" className="mt-2 text-2xl font-semibold tracking-tight text-foreground">Prepare an AI workforce role</h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">Context → conversation → action → approval → outcome. Publishing remains evidence-gated.</p>

            <div role="tablist" aria-label="Workforce activation steps" className="mt-6 flex flex-wrap gap-2">
                {steps.map((step, index) => (
                    <button
                        key={step}
                        type="button"
                        role="tab"
                        id={`${id}-${step.toLowerCase()}-tab`}
                        aria-controls={`${id}-${step.toLowerCase()}-panel`}
                        aria-selected={activeStep === index}
                        tabIndex={activeStep === index ? 0 : -1}
                        onClick={() => setActiveStep(index)}
                        onKeyDown={(event) => {
                            if (event.key === "ArrowRight") setActiveStep((activeStep + 1) % steps.length);
                            if (event.key === "ArrowLeft") setActiveStep((activeStep + steps.length - 1) % steps.length);
                        }}
                        className={`rounded-full px-3 py-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${activeStep === index ? "bg-primary text-primary-foreground" : "border border-border text-foreground hover:bg-muted"}`}
                    >
                        {index + 1}. {step}
                    </button>
                ))}
            </div>

            <div id={`${id}-${steps[activeStep].toLowerCase()}-panel`} role="tabpanel" aria-labelledby={`${id}-${steps[activeStep].toLowerCase()}-tab`} className="mt-6">
                {activeStep === 0 && (
                    <fieldset>
                        <legend className="font-medium text-foreground">First outcome</legend>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                            {outcomes.map(([value, label]) => (
                                <label key={value} className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-3 text-sm text-foreground">
                                    <input type="radio" name={`${id}-outcome`} value={value} checked={outcome === value} onChange={() => setOutcome(value)} />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}
                {activeStep === 1 && (
                    <fieldset>
                        <legend className="font-medium text-foreground">Governed role candidate</legend>
                        {roles.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No governed role candidate is available yet. The cockpit stays in preview.</p> : (
                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                {roles.map((role) => (
                                    <label key={role.key} className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-3 text-sm text-foreground">
                                        <input type="radio" name={`${id}-role`} value={role.key} checked={roleKey === role.key} onChange={() => setRoleKey(role.key)} />
                                        {role.title}
                                    </label>
                                ))}
                            </div>
                        )}
                    </fieldset>
                )}
                {activeStep === 2 && (
                    <fieldset>
                        <legend className="font-medium text-foreground">Connected channels</legend>
                        <p className="mt-1 text-sm text-muted-foreground">Only channels with external health evidence can become eligible after approval.</p>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                            {channels.map((channel) => (
                                <label key={channel.key} className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-3 text-sm text-foreground">
                                    <input type="checkbox" checked={selectedChannels.includes(channel.key)} onChange={() => toggleChannel(channel.key)} />
                                    <span>{channel.channel}</span>
                                    <span className="ml-auto text-xs text-muted-foreground">{channel.evidence_state}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}
                {activeStep === 3 && <p className="rounded-md border border-border bg-muted/30 p-4 text-sm text-muted-foreground">Run a sandbox simulation before requesting evaluation. Simulation fixtures never send a live provider request.</p>}
                {activeStep === 4 && (
                    <fieldset>
                        <legend className="font-medium text-foreground">Approval mode</legend>
                        <div className="mt-3 flex flex-wrap gap-3">
                            {["human_review", "policy_gate"].map((mode) => (
                                <label key={mode} className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-3 text-sm text-foreground">
                                    <input type="radio" name={`${id}-approval`} value={mode} checked={approvalMode === mode} onChange={() => setApprovalMode(mode)} />
                                    {mode === "human_review" ? "Human review" : "Policy gate"}
                                </label>
                            ))}
                        </div>
                        <button type="button" onClick={() => onSubmit?.({ role_key: roleKey, channel_keys: selectedChannels, simulation_case_ids: [outcome], approval_mode: approvalMode })} disabled={!roleKey} className="mt-5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                            Request evidence-gated activation
                        </button>
                    </fieldset>
                )}
            </div>
        </section>
    );
}
