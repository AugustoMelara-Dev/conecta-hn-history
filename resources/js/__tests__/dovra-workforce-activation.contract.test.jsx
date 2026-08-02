import "@testing-library/jest-dom/vitest";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import WorkforceActivationWizard from "@/Components/Workforce/WorkforceActivationWizard";
import WorkforceSimulationPanel from "@/Components/Workforce/WorkforceSimulationPanel";

describe("Dovra workforce activation contract", () => {
    it("keeps preview, sandbox simulation, and external evidence distinct", () => {
        render(
            <>
                <WorkforceActivationWizard
                    roles={[{ key: "support", title: "Support", status: "preview" }]}
                    onSubmit={() => undefined}
                />
                <WorkforceSimulationPanel
                    simulation={{ provider_label: "Simulation fixture", evidence_label: "Scenario source" }}
                    liveEvidence={{ verified: false, state: "pending_external_verification" }}
                />
            </>,
        );

        expect(screen.getByRole("tab", { name: /describe/i })).toBeVisible();
        expect(screen.getByRole("tab", { name: /publish/i })).toBeVisible();
        expect(screen.getByText(/sandbox simulation/i)).toBeVisible();
        expect(screen.getByText(/scenario source/i)).toBeVisible();
        expect(screen.getAllByText(/external verification is pending/i)).not.toHaveLength(0);
        expect(screen.queryByText(/all channels.*live/i)).not.toBeInTheDocument();
    });
});
