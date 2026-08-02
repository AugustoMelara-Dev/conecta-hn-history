import { readFile } from "node:fs/promises";

const files = [
    "resources/js/Pages/Tenant/Workforce.jsx",
    "resources/js/Components/Workforce/WorkforceActivationWizard.jsx",
    "resources/js/Components/Workforce/WorkforceSimulationPanel.jsx",
];

const source = await Promise.all(files.map((file) => readFile(file, "utf8")));
const joined = source.join("\n");
const required = [
    "Sandbox simulation",
    "External verification is pending",
    "evidence-gated",
    "role=\"tablist\"",
];

for (const marker of required) {
    if (!joined.includes(marker)) {
        throw new Error(`Dovra workforce smoke failed: missing ${marker}`);
    }
}

if (/all channels[^\n]{0,80}live/i.test(joined) || /guaranteed live/i.test(joined)) {
    throw new Error("Dovra workforce smoke failed: unsupported live claim found");
}

console.log("Dovra workforce smoke: preview, simulation, pending evidence, and keyboard steps are present.");
