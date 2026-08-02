import { execFileSync, spawnSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const argv = process.argv.slice(2);
const strict = argv.includes("--strict");
const json = argv.includes("--json");
const tenantOption = argv.find((argument) => argument.startsWith("--tenant="));
const tenant = tenantOption ? tenantOption.slice("--tenant=".length).trim().toLowerCase() : null;

if (argv.includes("--help") || argv.includes("-h")) {
    console.log("Usage: node scripts/qa/certify-dovra-workforce.mjs [--tenant=UUID] [--strict] [--json]");
    console.log("Runs structural Dovra workforce evidence checks; it never contacts providers or mutates a database.");
    process.exit(0);
}

const checks = [];
const missingEvidence = [];
const externalPending = [
    "Compatible PHP 8.4/Laravel 13 vendor runtime and freshly bootstrapped Composer graph",
    "Certified database migrations, PostgreSQL/RLS restricted-role proof, and cross-tenant runtime evidence",
    "Live AI/channel/billing providers, credentials, webhooks, DNS, payment endpoints, and public deployment",
    "Legal, privacy, security/compliance review and independent certification or audit sign-off",
    "Managed backup/restore drill, production observability destinations, SLOs, and incident ownership",
    "Real customer usage, traction, revenue, PMF, or investment-readiness evidence",
];

const requiredPaths = [
    "app/Console/Commands/ValidateDovraWorkforceCommand.php",
    "scripts/qa/certify-dovra-workforce.mjs",
    "tests/Feature/DevOps/DovraWorkforceIntegrationContractTest.php",
    "tests/Feature/DevOps/DovraWorkforceMigrationContractTest.php",
    "app/Application/BusinessGraph/BusinessGraphBackfill.php",
    "app/Application/Ai/Workforce/AgentRoleCatalog.php",
    "app/Application/Ai/Workforce/WorkforceAgentResolver.php",
    "app/Application/Automation/ToolPolicyEngine.php",
    "app/Application/Automation/AgentAction/AgentActionExecutor.php",
    "app/Application/Automation/AgentAction/AgentOutcomeRecorder.php",
    "app/Application/Channels/ConversationChannelBridge.php",
    "app/Application/Channels/ConversationChannelLink.php",
    "app/Application/Channels/ChannelDeliveryService.php",
    "app/Application/Discovery/AgentCapabilityProjection.php",
    "app/Domain/Billing/Global/WorkforceEntitlement.php",
    "app/Http/Controllers/Api/V1/TenantWorkforceController.php",
    "database/migrations/2026_08_02_210000_expand_dovra_business_graph.php",
    "database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php",
    "database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php",
    "database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php",
    "docs/dovra/product/dovra-global-ai-workforce-os-truth.md",
    "docs/dovra/acceptance-matrix-2026-08-01.md",
    "docs/dovra/launch-gates-2026-08-01.md",
    "docs/dovra/evidence/program-workforce-2026-08-02.md",
];

const programEvidencePaths = [
    "program-00-2026-07-28.md",
    "program-01-2026-07-28.md",
    "program-02-2026-07-28.md",
    "program-03-2026-07-28.md",
    "program-04-2026-07-28.md",
    "program-05-2026-07-29.md",
    "program-06-2026-08-01.md",
    "program-07-2026-08-01.md",
    "program-08-2026-08-01.md",
    "program-09-2026-08-01.md",
    "program-10-2026-08-01.md",
    "program-11-2026-08-01.md",
    "program-12-2026-08-01.md",
    "program-13-2026-08-01.md",
    "program-14-2026-08-01.md",
];

function absolute(relativePath) {
    return resolve(root, relativePath);
}

function source(relativePath) {
    const path = absolute(relativePath);
    if (!existsSync(path)) {
        return null;
    }

    try {
        return readFileSync(path, "utf8");
    } catch {
        return null;
    }
}

function addCheck(id, status, detail, evidence = []) {
    checks.push({ id, status, detail, evidence });
}

function pathsCheck() {
    const missing = requiredPaths.filter((path) => !existsSync(absolute(path)));
    if (missing.length > 0) {
        missingEvidence.push(...missing.map((path) => `file:${path}`));
    }
    addCheck(
        "repository_paths",
        missing.length === 0 ? "pass" : "fail",
        missing.length === 0 ? "Required workforce implementation, contract, migration, truth, and evidence paths exist." : `Missing paths: ${missing.join(", ")}`,
        requiredPaths,
    );
}

function commandContractCheck() {
    const content = source("app/Console/Commands/ValidateDovraWorkforceCommand.php") ?? "";
    const markers = [
        "dovra:validate-workforce",
        "{--tenant=",
        "{--dry-run",
        "{--strict",
        "{--json",
        "missing_evidence",
        "external_pending",
        "dirty_path_count",
        "can_certify_exact_artifact",
        "BusinessGraphBackfill",
        "dryRun: true",
        "cross_tenant_integrity",
    ];
    const missing = markers.filter((marker) => !content.includes(marker));
    if (missing.length > 0) {
        missingEvidence.push(...missing.map((marker) => `command:${marker}`));
    }
    addCheck(
        "validation_command_contract",
        missing.length === 0 ? "pass" : "fail",
        missing.length === 0 ? "Validation command exposes the required flags and evidence-first output contract." : `Missing command markers: ${missing.join(", ")}`,
        ["app/Console/Commands/ValidateDovraWorkforceCommand.php"],
    );
}

function sourceContractCheck(id, paths, markers) {
    const combined = paths.map((path) => source(path) ?? "").join("\n");
    const missing = markers.filter((marker) => !combined.includes(marker));
    if (missing.length > 0) {
        missingEvidence.push(...missing.map((marker) => `${id}:${marker}`));
    }
    addCheck(
        id,
        missing.length === 0 ? "pass" : "fail",
        missing.length === 0 ? "Required tenant, governance, replay, lineage, and evidence markers are present." : `Missing markers: ${missing.join(", ")}`,
        paths,
    );
}

function migrationContractCheck() {
    const migrations = {
        business_graph: {
            path: "database/migrations/2026_08_02_210000_expand_dovra_business_graph.php",
            markers: ["dovra_business_contexts", "dovra_business_facts", "dovra_business_edges", "tenant_id", "unique("],
        },
        action_outcome: {
            path: "database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php",
            markers: ["dovra_agent_action_audits", "dovra_agent_outcomes", "idempotency_key", "payload_hash", "ENABLE ROW LEVEL SECURITY", "FORCE ROW LEVEL SECURITY"],
        },
        channel_lineage: {
            path: "database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php",
            markers: ["dovra_conversation_channel_links", "lineage_idempotency_key", "external_thread_reference", "payload_hash", "current_setting", "WITH CHECK"],
        },
        activation_requests: {
            path: "database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php",
            markers: ["dovra_workforce_activation_requests", "tenant_id", "idempotency_key", "request_hash", "candidate_agent_version_id", "release_decision_id", "restrictOnDelete", "ENABLE ROW LEVEL SECURITY", "FORCE ROW LEVEL SECURITY", "current_setting", "WITH CHECK"],
        },
    };

    for (const [id, contract] of Object.entries(migrations)) {
        sourceContractCheck(`migration_${id}`, [contract.path], contract.markers);
    }

    const activation = source("app/Http/Controllers/Api/V1/TenantWorkforceController.php") ?? "";
    const forbiddenLiveWrite = /ai_agent_activations[\s\S]{0,240}(?:insert|update|upsert)/i.test(activation);
    const draftMarkers = ["lifecycle_status", "draft", "simulation", "dovra_workforce_activation_requests"];
    const missingDraftMarkers = draftMarkers.filter((marker) => !activation.includes(marker));
    if (forbiddenLiveWrite || missingDraftMarkers.length > 0) {
        missingEvidence.push(...missingDraftMarkers.map((marker) => `activation:${marker}`));
        if (forbiddenLiveWrite) {
            missingEvidence.push("activation:live_activation_write");
        }
    }
    addCheck(
        "activation_draft_only",
        !forbiddenLiveWrite && missingDraftMarkers.length === 0 ? "pass" : "fail",
        !forbiddenLiveWrite && missingDraftMarkers.length === 0 ? "Activation requests remain idempotent and draft/simulation-only; no live activation write is present." : "Activation contract is missing draft-only markers or contains a live activation write.",
        ["app/Http/Controllers/Api/V1/TenantWorkforceController.php", "database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php"],
    );
}

function programScanCheck() {
    const missing = programEvidencePaths.filter((path) => !existsSync(absolute(`docs/dovra/evidence/${path}`)));
    const acceptance = source("docs/dovra/acceptance-matrix-2026-08-01.md") ?? "";
    const launchGates = source("docs/dovra/launch-gates-2026-08-01.md") ?? "";
    const ledger = source("docs/dovra/implementation-ledger.md") ?? "";
    const missingAcceptance = Array.from({ length: 16 }, (_, index) => `AC${String(index + 1).padStart(2, "0")}`).filter((marker) => !acceptance.includes(marker));
    const missingGates = Array.from({ length: 18 }, (_, index) => `LG${String(index + 1).padStart(2, "0")}`).filter((marker) => !launchGates.includes(marker));
    const missingPrograms = Array.from({ length: 15 }, (_, index) => String(index).padStart(2, "0")).filter((code) => {
        const marker = new RegExp(`(?:Program\\s+${code}\\b|\\|\\s*${code}\\s)`, "u");

        return !marker.test(ledger);
    });
    const architectureDir = existsSync(absolute("tests/Architecture"));
    const problems = [
        ...missing.map((path) => `evidence:${path}`),
        ...missingAcceptance.map((marker) => `acceptance:${marker}`),
        ...missingGates.map((marker) => `launch_gate:${marker}`),
        ...missingPrograms.map((marker) => `ledger:${marker}`),
        ...(architectureDir ? [] : ["architecture:tests/Architecture"]),
    ];
    if (problems.length > 0) {
        missingEvidence.push(...problems);
    }
    addCheck(
        "program_00_14_structural_scan",
        problems.length === 0 ? "pass" : "fail",
        problems.length === 0 ? "Program 00–14 evidence files, AC01–AC16, LG01–LG18, and architecture surface are structurally present." : `Structural scan gaps: ${problems.join(", ")}`,
        ["docs/dovra/evidence", "docs/dovra/acceptance-matrix-2026-08-01.md", "docs/dovra/launch-gates-2026-08-01.md", "docs/dovra/implementation-ledger.md", "tests/Architecture"],
    );
}

function claimsBoundaryCheck() {
    const paths = [
        "docs/dovra/product/dovra-global-ai-workforce-os-truth.md",
        "docs/dovra/evidence/program-workforce-2026-08-02.md",
        "docs/dovra/launch-gates-2026-08-01.md",
    ];
    const content = paths.map((path) => source(path) ?? "").join("\n").toLowerCase();
    const markers = ["external_dependency", "unsupported_claim", "public production", "traction", "revenue", "investment"];
    const missing = markers.filter((marker) => !content.includes(marker));
    if (missing.length > 0) {
        missingEvidence.push(...missing.map((marker) => `claims:${marker}`));
    }
    addCheck(
        "claims_boundary_scan",
        missing.length === 0 ? "pass" : "fail",
        missing.length === 0 ? "Truth/evidence documents explicitly keep public production, compliance, traction, revenue, and investment claims evidence-bound." : `Missing claims boundary markers: ${missing.join(", ")}`,
        paths,
    );
}

function uiSmokeCheck() {
    const script = absolute("scripts/qa/dovra-workforce-smoke.mjs");
    if (!existsSync(script)) {
        addCheck("ui_workforce_smoke", "blocked", "Existing workforce UI smoke script is not available; no UI result is promoted.", ["scripts/qa/dovra-workforce-smoke.mjs"]);
        return;
    }
    const result = spawnSync(process.execPath, [script], {
        cwd: root,
        encoding: "utf8",
        stdio: ["ignore", "pipe", "pipe"],
        shell: false,
    });
    const detail = result.status === 0
        ? (result.stdout?.trim() || "Workforce UI smoke passed.")
        : `Workforce UI smoke exited ${result.status}: ${(result.stderr || result.stdout || "unknown error").trim()}`;
    addCheck("ui_workforce_smoke", result.status === 0 ? "pass" : "fail", detail, ["scripts/qa/dovra-workforce-smoke.mjs"]);
    if (result.status !== 0) {
        missingEvidence.push("ui:workforce_smoke");
    }
}

function gitDirtyPathCount() {
    try {
        const output = execFileSync("git", ["status", "--porcelain=v1"], {
            cwd: root,
            encoding: "utf8",
            stdio: ["ignore", "pipe", "ignore"],
        });
        const paths = output.trim() === "" ? [] : output.trim().split(/\r?\n/).filter(Boolean);
        return { count: paths.length, error: null };
    } catch (error) {
        return { count: null, error: error instanceof Error ? error.message : String(error) };
    }
}

function artifactCheck(dirty) {
    if (dirty.count === null) {
        addCheck("exact_artifact", "blocked", `Git status unavailable: ${dirty.error ?? "unknown error"}.`, []);
        return;
    }
    addCheck(
        "exact_artifact",
        dirty.count === 0 ? "pass" : "blocked",
        dirty.count === 0 ? "No dirty paths; exact artifact may be assessed subject to all other gates." : `Dirty checkout contains ${dirty.count} paths; exact-artifact certification remains blocked.`,
        [],
    );
}

if (tenant !== null && !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(tenant)) {
    missingEvidence.push("tenant:valid_uuid");
    addCheck("tenant_option", "fail", "--tenant must be a UUID when supplied.", []);
} else {
    addCheck("tenant_option", "pass", tenant === null ? "No tenant filter supplied; structural checks remain tenant-aware." : "Tenant option is syntactically valid.", []);
}

pathsCheck();
commandContractCheck();
sourceContractCheck(
    "integration_chain_sources",
    [
        "app/Application/BusinessGraph/BusinessGraphBackfill.php",
        "app/Application/Ai/Workforce/WorkforceAgentResolver.php",
        "app/Http/Controllers/Api/V1/TenantWorkforceController.php",
        "app/Application/Automation/AgentAction/AgentActionExecutor.php",
        "app/Application/Automation/AgentAction/AgentOutcomeRecorder.php",
        "app/Application/Channels/ConversationChannelBridge.php",
        "app/Application/Discovery/AgentCapabilityProjection.php",
        "app/Domain/Billing/Global/WorkforceEntitlement.php",
    ],
    ["tenantId", "BusinessGraph", "simulation", "idempotency", "payload_hash", "lineage", "agent_capability", "provider_event_id"],
);
migrationContractCheck();
programScanCheck();
claimsBoundaryCheck();
uiSmokeCheck();
const dirty = gitDirtyPathCount();
artifactCheck(dirty);

const uniqueMissing = [...new Set(missingEvidence)];
const internalFailure = checks.some((check) => check.status === "fail") || uniqueMissing.length > 0;
const internalBlocked = checks.some((check) => check.status === "blocked") || dirty.count !== 0;
const status = internalFailure ? "fail" : (internalBlocked || externalPending.length > 0 ? "blocked" : "pass");
const canCertifyExactArtifact = status === "pass"
    && dirty.count === 0
    && uniqueMissing.length === 0
    && externalPending.length === 0;
const report = {
    schema: 1,
    command: "node scripts/qa/certify-dovra-workforce.mjs",
    status,
    tenant,
    strict,
    checks,
    missing_evidence: uniqueMissing,
    external_pending: externalPending,
    dirty_path_count: dirty.count,
    can_certify_exact_artifact: canCertifyExactArtifact,
    safety: {
        database_mutations: false,
        external_provider_calls: false,
        public_production_claim: false,
        compliance_or_certification_claim: false,
        traction_revenue_or_investment_claim: false,
    },
};

if (json) {
    console.log(JSON.stringify(report, null, 2));
} else {
    console.log(`status: ${status}`);
    console.log(`checks: ${checks.length}`);
    console.log(`missing_evidence: ${uniqueMissing.length}`);
    console.log(`external_pending: ${externalPending.length}`);
    console.log(`dirty_path_count: ${dirty.count ?? "unknown"}`);
    console.log(`can_certify_exact_artifact: ${canCertifyExactArtifact}`);
    for (const check of checks) {
        console.log(`[${check.status}] ${check.id}: ${check.detail}`);
    }
}

if (internalFailure || (strict && status !== "pass")) {
    process.exitCode = 1;
} else {
    process.exitCode = 0;
}
