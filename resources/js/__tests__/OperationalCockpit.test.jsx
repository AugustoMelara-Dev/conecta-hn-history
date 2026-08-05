import "@testing-library/jest-dom/vitest";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import OperationalCockpit from "@/Components/Dashboard/OperationalCockpit";

const snapshot = {
    currency: "CAD",
    verifiedOutcomeMinor: 12500,
    attentionRequired: 2,
    activeOpportunities: 3,
    agingOpportunities: 1,
    upcomingOrders: 2,
    upcomingReservations: 1,
    overdueQuotes: 1,
    failedImports: 1,
    aiAllowance: 4,
    aiCostToday: 0.25,
    healthIssues: [
        {
            code: "product_price_missing",
            severity: "critical",
            evidenceLabel: "Travel mug",
            message: "Product needs a valid price and currency.",
            remediationUrl: "/app/store/products/1/edit",
        },
    ],
    nextAction: "resolve_failed_imports",
    nextActionLabel: "Resolve failed catalog imports",
    nextActionUrl: "/app/store/products",
    degradedSources: [],
};

describe("OperationalCockpit", () => {
    it("renders outcomes below action queues with tenant currency and remediation links", () => {
        render(<OperationalCockpit snapshot={snapshot} status="ready" />);

        expect(screen.getByRole("heading", { name: "Operational cockpit" })).toBeInTheDocument();
        expect(screen.getByRole("link", { name: "Resolve failed catalog imports" }))
            .toHaveAttribute("href", "/app/store/products");
        expect(screen.getByText(/CA\$125\.00/)).toBeInTheDocument();
        expect(screen.getByRole("link", { name: /Travel mug/ }))
            .toHaveAttribute("href", "/app/store/products/1/edit");
        expect(screen.getByText("1 overdue quote")).toBeInTheDocument();
    });

    it.each([
        ["empty", "Operations are clear"],
        ["degraded", "Some sources are temporarily unavailable"],
        ["forbidden", "You do not have permission to view this cockpit"],
        ["loading", "Loading operational cockpit"],
    ])("renders the %s state accessibly", (status, copy) => {
        render(<OperationalCockpit snapshot={{ ...snapshot, degradedSources: ["ai_usage_logs"] }} status={status} />);

        expect(screen.getByText(copy)).toBeInTheDocument();
    });
});
