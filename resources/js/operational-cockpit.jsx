import { createRoot } from "react-dom/client";

import OperationalCockpit from "@/Components/Dashboard/OperationalCockpit";

const mountOperationalCockpits = () => {
    document.querySelectorAll("[data-operational-cockpit]").forEach((element) => {
        if (element.dataset.mounted === "true") {
            return;
        }

        const payload = element.querySelector("[data-operational-cockpit-payload]");
        const target = element.querySelector("[data-operational-cockpit-root]");
        if (!payload || !target) {
            return;
        }

        const props = JSON.parse(payload.textContent || "{}");
        createRoot(target).render(<OperationalCockpit {...props} />);
        element.dataset.mounted = "true";
    });
};

document.addEventListener("DOMContentLoaded", mountOperationalCockpits);
document.addEventListener("livewire:navigated", mountOperationalCockpits);
