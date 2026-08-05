import {
    existsSync,
    lstatSync,
    realpathSync,
    readFileSync,
} from "node:fs";
import { createHash } from "node:crypto";
import {
    isAbsolute,
    join,
    posix,
    relative,
    resolve,
    win32,
} from "node:path";
import { fileURLToPath } from "node:url";

export const PILOT_PACKAGE_SCHEMA_VERSION = 1;
export const PILOT_MANIFEST_PATH = "docs/dovra/pilot/dovra-program13/pilot-analytics-manifest.json";
export const PILOT_READINESS_PATH = "docs/dovra/pilot/dovra-program13/pilot-readiness-package.md";
export const PILOT_TEMPLATE_DIGESTS = Object.freeze({
    [PILOT_MANIFEST_PATH]: "017826b7e96060a0d9e12451e2874e78c8bc2d25b6f412ff06cc274de30d1e05",
    [PILOT_READINESS_PATH]: "90f2ee3b0e0234474da8c1b807c9b83ab7875c7193a8d8a542bf238b07b12433",
});

const requiredManifestFields = [
    "schema_version",
    "package_status",
    "referenced_assets",
    "analytics_rules",
    "claims_boundary",
    "metrics",
];
const requiredMetricFields = [
    "id",
    "name",
    "category",
    "authority",
    "source",
    "window",
    "numerator",
    "denominator",
    "value",
    "status",
    "evidence",
];
const metricCategories = new Set(["activation", "first_value", "retention", "outcome"]);
const attributionStages = new Set(["prepared", "influenced", "verified"]);
const verifiedAuthorities = new Set(["verified_commercial_outcomes", "order_paid", "order_refunded"]);
const claimFields = ["active_pilot", "customers", "traction", "revenue", "pmf", "investment_ready"];
const externalPending = Object.freeze([
    "Named participant consent and a bounded pilot scope",
    "Reachable staging/runtime environment and provider approvals",
    "Operator-supplied analytics exports with cohort, window, and source provenance",
    "Independent review of privacy, safety, and any applicable compliance obligations",
    "Verified commercial outcomes and order-paid/order-refunded evidence, if any",
    "Customer, traction, revenue, PMF, and investment evidence from outside the repository",
]);

class StrictJsonParser {
    constructor(text) {
        this.text = text;
        this.position = 0;
    }

    parse() {
        const value = this.parseValue();
        this.skipWhitespace();
        if (this.position !== this.text.length) {
            this.error("unexpected trailing characters");
        }

        return value;
    }

    parseValue() {
        this.skipWhitespace();
        const character = this.text[this.position];

        if (character === "{") return this.parseObject();
        if (character === "[") return this.parseArray();
        if (character === '"') return this.parseString();
        if (this.text.startsWith("true", this.position)) {
            this.position += 4;
            return true;
        }
        if (this.text.startsWith("false", this.position)) {
            this.position += 5;
            return false;
        }
        if (this.text.startsWith("null", this.position)) {
            this.position += 4;
            return null;
        }

        return this.parseNumber();
    }

    parseObject() {
        this.expect("{");
        const object = {};
        const keys = new Set();
        this.skipWhitespace();
        if (this.consume("}")) return object;

        while (true) {
            this.skipWhitespace();
            if (this.text[this.position] !== '"') this.error("object key must be a string");
            const key = this.parseString();
            if (keys.has(key)) this.error(`duplicate object key: ${key}`);
            keys.add(key);
            this.skipWhitespace();
            this.expect(":");
            object[key] = this.parseValue();
            this.skipWhitespace();
            if (this.consume("}")) return object;
            this.expect(",");
        }
    }

    parseArray() {
        this.expect("[");
        const array = [];
        this.skipWhitespace();
        if (this.consume("]")) return array;

        while (true) {
            array.push(this.parseValue());
            this.skipWhitespace();
            if (this.consume("]")) return array;
            this.expect(",");
        }
    }

    parseString() {
        const start = this.position;
        this.expect('"');

        while (this.position < this.text.length) {
            const character = this.text[this.position];
            if (character === "\\") {
                this.position += 2;
                continue;
            }
            if (character === '"') {
                this.position += 1;
                const raw = this.text.slice(start, this.position);
                try {
                    return JSON.parse(raw);
                } catch {
                    this.error("invalid JSON string escape");
                }
            }
            if (character.charCodeAt(0) < 0x20) this.error("control character in JSON string");
            this.position += 1;
        }

        this.error("unterminated JSON string");
    }

    parseNumber() {
        const candidate = this.text.slice(this.position).match(/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/u);
        if (!candidate) this.error("invalid JSON value");
        this.position += candidate[0].length;
        const number = Number(candidate[0]);
        if (!Number.isFinite(number)) this.error("non-finite JSON number");

        return number;
    }

    skipWhitespace() {
        while (/[\u0009\u000a\u000d\u0020]/u.test(this.text[this.position] ?? "")) this.position += 1;
    }

    consume(character) {
        if (this.text[this.position] !== character) return false;
        this.position += 1;
        return true;
    }

    expect(character) {
        if (!this.consume(character)) this.error(`expected ${character}`);
    }

    error(message) {
        throw new Error(`${message} at offset ${this.position}`);
    }
}

export function parseStrictJson(text) {
    return new StrictJsonParser(text).parse();
}

function posixPath(path) {
    return path.replaceAll("\\", "/");
}

function finding(code, path, detail) {
    return { code, path, detail };
}

function isInsideRoot(root, target) {
    const child = relative(root, target);
    const normalizedChild = posixPath(child);

    return child !== "" && child !== ".." && !normalizedChild.startsWith("../") && !isAbsolute(child) && !win32.isAbsolute(child);
}

export function resolvePackageReference(root, candidate) {
    if (typeof candidate !== "string" || candidate.trim() === "") {
        return { finding: finding("ASSET_PATH_INVALID", "", "Referenced asset path must be a non-empty relative string.") };
    }
    if (isAbsolute(candidate) || win32.isAbsolute(candidate) || posix.isAbsolute(candidate)) {
        return { finding: finding("ASSET_PATH_INVALID", posixPath(candidate), "Absolute asset paths are not allowed.") };
    }

    const normalized = posixPath(candidate);
    const target = resolve(root, normalized);
    if (!isInsideRoot(root, target)) {
        return { finding: finding("ASSET_PATH_INVALID", normalized, "Asset path escapes the package root.") };
    }

    let current = root;
    for (const segment of normalized.split("/")) {
        if (!segment || segment === ".") continue;
        current = join(current, segment);
        let stats;
        try {
            stats = lstatSync(current);
        } catch {
            return { finding: finding("ASSET_MISSING", normalized, "Referenced asset does not exist.") };
        }
        if (stats.isSymbolicLink()) {
            return { finding: finding("ASSET_SYMLINK", normalized, "Symlink or junction references are not allowed.") };
        }
    }

    let finalStats;
    try {
        finalStats = lstatSync(target);
    } catch {
        return { finding: finding("ASSET_MISSING", normalized, "Referenced asset does not exist.") };
    }
    if (!finalStats.isFile()) {
        return { finding: finding("ASSET_NOT_FILE", normalized, "Referenced asset must resolve to a regular file.") };
    }

    return { path: normalized, absolute: target };
}

function topologyFinding(code, path, detail) {
    return { code, path, detail };
}

export function validateManifestTopology(root) {
    const findings = [];
    let rootStats;
    try {
        rootStats = lstatSync(root);
    } catch {
        return {
            findings: [topologyFinding("TOPOLOGY_ROOT_INVALID", ".", "Package root does not exist.")],
            manifestExists: false,
            manifestAbsolutePath: join(root, PILOT_MANIFEST_PATH),
        };
    }
    if (!rootStats.isDirectory()) {
        findings.push(topologyFinding("TOPOLOGY_ROOT_INVALID", ".", "Package root must be a directory."));
    }
    if (rootStats.isSymbolicLink()) {
        findings.push(topologyFinding("TOPOLOGY_LINK", ".", "Package root must not be a symlink or junction."));
    }

    let canonicalRoot;
    try {
        canonicalRoot = realpathSync(root);
    } catch {
        findings.push(topologyFinding("TOPOLOGY_ROOT_INVALID", ".", "Package root canonical path is unavailable."));
    }
    if (findings.length > 0) {
        return {
            findings,
            manifestExists: false,
            manifestAbsolutePath: join(root, PILOT_MANIFEST_PATH),
        };
    }

    let current = root;
    const segments = PILOT_MANIFEST_PATH.split("/");
    for (const [index, segment] of segments.entries()) {
        current = join(current, segment);
        let stats;
        try {
            stats = lstatSync(current);
        } catch {
            return {
                findings,
                manifestExists: false,
                manifestAbsolutePath: current,
            };
        }
        if (stats.isSymbolicLink()) {
            findings.push(topologyFinding("TOPOLOGY_LINK", posixPath(relative(root, current)), "Manifest parent/file chain must not contain symlinks, junctions, or reparse points."));
            return {
                findings,
                manifestExists: false,
                manifestAbsolutePath: current,
            };
        }
        let canonicalCurrent;
        try {
            canonicalCurrent = realpathSync(current);
        } catch {
            findings.push(topologyFinding("TOPOLOGY_INVALID", posixPath(relative(root, current)), "Manifest path canonicalization failed."));
            return {
                findings,
                manifestExists: false,
                manifestAbsolutePath: current,
            };
        }
        if (canonicalCurrent !== canonicalRoot && !isInsideRoot(canonicalRoot, canonicalCurrent)) {
            findings.push(topologyFinding("TOPOLOGY_ESCAPE", posixPath(relative(root, current)), "Manifest canonical path escapes the intended package root."));
            return {
                findings,
                manifestExists: false,
                manifestAbsolutePath: current,
            };
        }
        if (index === segments.length - 1 && !stats.isFile()) {
            findings.push(topologyFinding("MANIFEST_NOT_FILE", PILOT_MANIFEST_PATH, "Manifest path must resolve to a regular file."));
            return {
                findings,
                manifestExists: false,
                manifestAbsolutePath: current,
            };
        }
    }

    return {
        findings,
        manifestExists: true,
        manifestAbsolutePath: current,
    };
}

function addFinding(findings, code, path, detail) {
    findings.push(finding(code, path, detail));
}

function requireObject(value, path, findings) {
    if (value === null || typeof value !== "object" || Array.isArray(value)) {
        addFinding(findings, "FIELD_TYPE_INVALID", path, "Expected a JSON object.");
        return false;
    }

    return true;
}

function requireNonEmptyString(value, path, findings) {
    if (typeof value !== "string" || value.trim() === "") {
        addFinding(findings, "FIELD_REQUIRED", path, "Expected a non-empty string.");
        return false;
    }

    return true;
}

const claimNegationPattern = /\b(?:no|not|never|without|cannot|does not|do not|isn't|aren't|wasn't|weren't|blocked|unavailable|missing|placeholder|future|if any|not evidence|not a|not imply|not claim|not claimed|not certify)\b/iu;
const affirmativeClaimPatterns = [
    /(?:\$|usd\s*)\s*\d[\d,.]*|\b\d[\d,.]*\s*(?:usd|mrr|arr)\b/iu,
    /\b(?:\d[\d,.]*|one|two|three|four|five|six|seven|eight|nine|ten)\s+active\s+customers?\b/iu,
    /\b(?:only|just)\s+(?:\d[\d,.]*|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:active\s+)?customers?\b/iu,
    /\b(?:observed|recorded|measured|validated|achieved|generated|secured|raised|funded|demonstrated|proved)\b[\s\S]{0,140}\b(?:pilot|pilots|customer|customers|participant|participants|traction|revenue|mrr|arr|pmf|product-market\s+fit|investment-ready|investment\s+readiness|funded|raised)\b/iu,
    /\b(?:\d[\d,.]*|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:customers?|participants?|pilots?)\b[\s\S]{0,100}\b(?:completed|converted|enrolled|joined|retained|renewed|paid|purchased|activated|demonstrated|proved|showed)\b/iu,
    /\b(?:a|an|the|our|this)\s+(?:active|current|live|running|launched)\s+(?:pilot|pilots|customer|customers|participant|participants)\b/iu,
    /\b(?:we|dovra|this package|our pilot|the pilot)\s+(?:have|has|are|is|was|were|shows?|demonstrates?|includes?|contains?|enrolls?)\b[\s\S]{0,140}\b(?:customer|customers|participant|participants|traction|revenue|mrr|arr|pmf|product-market\s+fit|investment-ready|investment\s+readiness|funded|raised)\b/iu,
];

function scanClaimText(value, path, findings) {
    if (typeof value !== "string") return;
    for (const sentence of value.split(/[\n.!?;:]+/u)) {
        const normalized = sentence.replaceAll("`", " ").replaceAll("*", " ").trim();
        if (normalized === "" || normalized.startsWith("#")) continue;
        if (claimNegationPattern.test(normalized)) continue;
        const pattern = affirmativeClaimPatterns.find((candidate) => candidate.test(normalized));
        if (pattern) {
            addFinding(findings, "CLAIM_TEXT_INVALID", path, "Affirmative active-pilot, customer, traction, revenue, PMF, or investment claim is not allowed in a repository template.");
            return;
        }
    }
}

function scanManifestClaimFields(manifest, findings) {
    for (const [index, metric] of (Array.isArray(manifest.metrics) ? manifest.metrics : []).entries()) {
        if (metric === null || typeof metric !== "object" || Array.isArray(metric)) continue;
        for (const field of ["id", "name", "category", "authority", "source", "window", "numerator", "denominator", "status", "evidence"]) {
            if (typeof metric[field] === "string") scanClaimText(metric[field], `manifest.metrics[${index}].${field}`, findings);
        }
        if (metric.evidence !== null && metric.evidence !== undefined && typeof metric.evidence === "object") {
            for (const field of ["id", "uri"]) {
                if (typeof metric.evidence[field] === "string") scanClaimText(metric.evidence[field], `manifest.metrics[${index}].evidence.${field}`, findings);
            }
        }
    }
    if (Array.isArray(manifest.external_pending)) {
        for (const [index, value] of manifest.external_pending.entries()) scanClaimText(value, `manifest.external_pending[${index}]`, findings);
    }
}

function templateDigest(text) {
    return createHash("sha256").update(text.replaceAll("\r\n", "\n"), "utf8").digest("hex");
}

function templateIntegrityFile(path, text, findings) {
    const expected = PILOT_TEMPLATE_DIGESTS[path];
    const observed = typeof text === "string" ? templateDigest(text) : null;
    if (observed !== expected) {
        addFinding(
            findings,
            "TEMPLATE_DRIFT",
            path,
            `Reviewed template SHA-256 mismatch; expected ${expected}, observed ${observed ?? "missing"}.`,
        );
    }

    return {
        path,
        expected_sha256: expected,
        observed_sha256: observed,
    };
}

function emptyTemplateIntegrity(status = "fail") {
    return {
        status,
        files: Object.keys(PILOT_TEMPLATE_DIGESTS).map((path) => templateIntegrityFile(path, null, [])),
        readinessText: null,
    };
}

function validateTemplateIntegrity(root, manifestText, findings) {
    const files = [templateIntegrityFile(PILOT_MANIFEST_PATH, manifestText, findings)];
    let readinessText = null;
    const resolved = resolvePackageReference(root, PILOT_READINESS_PATH);
    if (resolved.finding) {
        addFinding(findings, resolved.finding.code, resolved.finding.path, resolved.finding.detail);
    } else {
        try {
            readinessText = readFileSync(resolved.absolute, "utf8");
        } catch {
            addFinding(findings, "READINESS_MISSING", PILOT_READINESS_PATH, "Pilot readiness document cannot be read.");
        }
    }
    files.push(templateIntegrityFile(PILOT_READINESS_PATH, readinessText, findings));

    return {
        status: files.every((file) => file.observed_sha256 === file.expected_sha256) ? "pass" : "fail",
        files,
        readinessText,
    };
}

function validateManifestShape(manifest, findings) {
    if (!requireObject(manifest, "manifest", findings)) return;

    for (const field of requiredManifestFields) {
        if (!Object.hasOwn(manifest, field)) addFinding(findings, "MANIFEST_FIELD_MISSING", `manifest.${field}`, "Required manifest field is missing.");
    }
    const allowedFields = new Set(requiredManifestFields);
    for (const field of Object.keys(manifest)) {
        if (!allowedFields.has(field)) addFinding(findings, "MANIFEST_FIELD_UNKNOWN", `manifest.${field}`, "Unknown manifest field is not permitted.");
    }
    if (manifest.schema_version !== PILOT_PACKAGE_SCHEMA_VERSION) addFinding(findings, "SCHEMA_VERSION_INVALID", "manifest.schema_version", "Unsupported pilot package schema version.");
    if (manifest.package_status !== "repository_template") addFinding(findings, "PACKAGE_STATUS_INVALID", "manifest.package_status", "Only repository_template is certifiable by this package.");
}

function validateClaimsBoundary(manifest, findings) {
    const claims = manifest.claims_boundary;
    if (!requireObject(claims, "manifest.claims_boundary", findings)) return;
    for (const field of claimFields) {
        if (!Object.hasOwn(claims, field)) {
            addFinding(findings, "CLAIM_BOUNDARY_INVALID", `manifest.claims_boundary.${field}`, "Every unsupported-claim flag must be present and false.");
        } else if (claims[field] !== false) {
            addFinding(findings, "CLAIM_BOUNDARY_INVALID", `manifest.claims_boundary.${field}`, "Active pilot, customer, traction, revenue, PMF, and investment claims are not allowed.");
        }
    }
    for (const field of Object.keys(claims)) {
        if (!claimFields.includes(field)) addFinding(findings, "CLAIM_BOUNDARY_INVALID", `manifest.claims_boundary.${field}`, "Unknown claim boundary field.");
    }
}

function validateAnalyticsRules(manifest, findings) {
    const rules = manifest.analytics_rules;
    if (!requireObject(rules, "manifest.analytics_rules", findings)) return;
    const expected = {
        prepared_authority: "interactions.type",
        influenced_authority: "interactions.utm_*",
        verified_authority: "verified_commercial_outcomes",
    };
    for (const [field, value] of Object.entries(expected)) {
        if (rules[field] !== value) addFinding(findings, "ANALYTICS_RULE_INVALID", `manifest.analytics_rules.${field}`, `Expected ${value}.`);
    }
    if (!Array.isArray(rules.verified_revenue_authorities) || rules.verified_revenue_authorities.slice().sort().join("|") !== ["order_paid", "order_refunded"].join("|")) {
        addFinding(findings, "ANALYTICS_RULE_INVALID", "manifest.analytics_rules.verified_revenue_authorities", "Verified revenue authority must be order_paid and order_refunded only.");
    }
    if (!Array.isArray(rules.prohibited_revenue_sources) || !["interactions", "utm", "metadata"].every((value) => rules.prohibited_revenue_sources.includes(value))) {
        addFinding(findings, "ANALYTICS_RULE_INVALID", "manifest.analytics_rules.prohibited_revenue_sources", "Interactions, UTM, and metadata amounts must be prohibited revenue sources.");
    }
}

function validateAssets(manifest, root, findings) {
    if (!Array.isArray(manifest.referenced_assets) || manifest.referenced_assets.length === 0) {
        addFinding(findings, "ASSETS_INVALID", "manifest.referenced_assets", "At least one referenced asset is required.");
        return;
    }
    const ids = new Set();
    for (const [index, asset] of manifest.referenced_assets.entries()) {
        const path = `manifest.referenced_assets[${index}]`;
        if (!requireObject(asset, path, findings)) continue;
        if (!requireNonEmptyString(asset.id, `${path}.id`, findings)) continue;
        if (ids.has(asset.id)) addFinding(findings, "ASSET_ID_DUPLICATE", `${path}.id`, "Referenced asset IDs must be unique.");
        ids.add(asset.id);
        if (!requireNonEmptyString(asset.path, `${path}.path`, findings)) continue;
        const resolved = resolvePackageReference(root, asset.path);
        if (resolved.finding) addFinding(findings, resolved.finding.code, resolved.finding.path, resolved.finding.detail);
    }
}

function validateMetric(metric, index, findings, packageStatus) {
    const path = `manifest.metrics[${index}]`;
    if (!requireObject(metric, path, findings)) return null;
    for (const field of requiredMetricFields) {
        if (!Object.hasOwn(metric, field)) addFinding(findings, "METRIC_FIELD_MISSING", `${path}.${field}`, "Required metric field is missing.");
    }
    const allowedFields = new Set([...requiredMetricFields, "attribution_stage"]);
    for (const field of Object.keys(metric)) {
        if (!allowedFields.has(field)) addFinding(findings, "METRIC_FIELD_UNKNOWN", `${path}.${field}`, "Unknown metric field is not permitted.");
    }
    for (const field of requiredMetricFields.filter((candidate) => !["value", "evidence"].includes(candidate))) {
        requireNonEmptyString(metric[field], `${path}.${field}`, findings);
    }
    if (!metricCategories.has(metric.category)) addFinding(findings, "METRIC_CATEGORY_INVALID", `${path}.category`, "Metric category must be activation, first_value, retention, or outcome.");
    if (metric.status !== "external_input_required") addFinding(findings, "METRIC_STATUS_INVALID", `${path}.status`, "Repository template metrics must remain external_input_required.");
    if (packageStatus === "repository_template") {
        if (metric.value !== null) addFinding(findings, "METRIC_VALUE_FORBIDDEN", `${path}.value`, "repository_template metrics must keep value null; measured packages require a different package status/schema.");
        if (metric.evidence !== null) addFinding(findings, "METRIC_EVIDENCE_FORBIDDEN", `${path}.evidence`, "repository_template metrics must keep evidence null; measured packages require a different package status/schema.");
    } else if (metric.value !== null && (!requireObject(metric.evidence, `${path}.evidence`, findings) || !requireNonEmptyString(metric.evidence.id, `${path}.evidence.id`, findings) || !requireNonEmptyString(metric.evidence.uri, `${path}.evidence.uri`, findings))) {
        addFinding(findings, "METRIC_EVIDENCE_REQUIRED", `${path}.value`, "Non-null metric values require evidence.id and evidence.uri.");
    } else if (metric.value === null && metric.evidence !== null) {
        addFinding(findings, "METRIC_EVIDENCE_INVALID", `${path}.evidence`, "Null metric values must not carry evidence claims.");
    }
    if (metric.category === "outcome") {
        if (!attributionStages.has(metric.attribution_stage)) {
            addFinding(findings, "ATTRIBUTION_STAGE_INVALID", `${path}.attribution_stage`, "Outcome metrics must declare prepared, influenced, or verified.");
        } else if (metric.attribution_stage === "prepared" && metric.authority !== "interactions.type") {
            addFinding(findings, "ATTRIBUTION_AUTHORITY_INVALID", `${path}.authority`, "Prepared outcomes are authoritative only from interactions.type.");
        } else if (metric.attribution_stage === "influenced" && metric.authority !== "interactions.utm_*") {
            addFinding(findings, "ATTRIBUTION_AUTHORITY_INVALID", `${path}.authority`, "Influenced outcomes are authoritative only from interactions.utm_*.");
        } else if (metric.attribution_stage === "verified" && !verifiedAuthorities.has(metric.authority)) {
            addFinding(findings, "ATTRIBUTION_AUTHORITY_INVALID", `${path}.authority`, "Verified outcomes/revenue are authoritative only from verified_commercial_outcomes, order_paid, or order_refunded.");
        }
    } else if (Object.hasOwn(metric, "attribution_stage")) {
        addFinding(findings, "ATTRIBUTION_STAGE_INVALID", `${path}.attribution_stage`, "Only outcome metrics may declare an attribution stage.");
    }
    if (/revenue/i.test(metric.id) && !verifiedAuthorities.has(metric.authority)) {
        addFinding(findings, "REVENUE_AUTHORITY_INVALID", `${path}.authority`, "Revenue may not derive from interactions, UTM fields, or metadata.");
    }

    return metric;
}

function validateMetrics(manifest, findings) {
    if (!Array.isArray(manifest.metrics) || manifest.metrics.length === 0) {
        addFinding(findings, "METRICS_INVALID", "manifest.metrics", "At least one metric is required.");
        return { stages: [], verifiedAuthorities: [] };
    }
    const ids = new Set();
    const stages = new Set();
    const authorities = new Set();
    for (const [index, metric] of manifest.metrics.entries()) {
        const valid = validateMetric(metric, index, findings, manifest.package_status);
        if (!valid) continue;
        if (ids.has(valid.id)) addFinding(findings, "METRIC_ID_DUPLICATE", `manifest.metrics[${index}].id`, "Metric IDs must be unique.");
        ids.add(valid.id);
        if (valid.category === "outcome") stages.add(valid.attribution_stage);
        if (valid.attribution_stage === "verified") authorities.add(valid.authority);
    }
    for (const stage of attributionStages) {
        if (!stages.has(stage)) addFinding(findings, "ATTRIBUTION_STAGE_MISSING", "manifest.metrics", `At least one ${stage} metric is required.`);
    }
    for (const authority of verifiedAuthorities) {
        if (!authorities.has(authority)) addFinding(findings, "VERIFIED_AUTHORITY_MISSING", "manifest.metrics", `Verified authority ${authority} is required.`);
    }

    return { stages: [...stages].sort(), verifiedAuthorities: [...authorities].sort() };
}

export function certifyPilotPackage(root) {
    const packageRoot = resolve(root);
    const findings = [];
    const topology = validateManifestTopology(packageRoot);
    findings.push(...topology.findings);
    let manifest = null;
    let manifestSource = null;

    if (topology.findings.length > 0) {
        // Fail closed: do not read a manifest through an unsafe root/parent/file link.
    } else if (!topology.manifestExists || !existsSync(topology.manifestAbsolutePath)) {
        addFinding(findings, "MANIFEST_MISSING", PILOT_MANIFEST_PATH, "Pilot analytics manifest is missing.");
    } else {
        try {
            manifestSource = readFileSync(topology.manifestAbsolutePath, "utf8");
            manifest = parseStrictJson(manifestSource);
        } catch (error) {
            addFinding(findings, "MANIFEST_JSON_INVALID", PILOT_MANIFEST_PATH, error instanceof Error ? error.message : String(error));
        }
    }

    let attribution = { stages: [], verifiedAuthorities: [] };
    if (manifest !== null) {
        validateManifestShape(manifest, findings);
        validateClaimsBoundary(manifest, findings);
        validateAnalyticsRules(manifest, findings);
        validateAssets(manifest, packageRoot, findings);
        attribution = validateMetrics(manifest, findings);
        scanManifestClaimFields(manifest, findings);
    }
    const templateIntegrity = topology.findings.length === 0
        ? validateTemplateIntegrity(packageRoot, manifestSource, findings)
        : emptyTemplateIntegrity();
    if (templateIntegrity.readinessText !== null) scanClaimText(templateIntegrity.readinessText, PILOT_READINESS_PATH, findings);

    const status = findings.length === 0 ? "PASS" : "FAIL";
    const unsupportedClaimsPromoted = findings.some((item) => item.code === "CLAIM_TEXT_INVALID" || item.code === "CLAIM_BOUNDARY_INVALID");
    const templateDrift = findings.some((item) => item.code === "TEMPLATE_DRIFT");
    return {
        schema_version: PILOT_PACKAGE_SCHEMA_VERSION,
        kind: "dovra-pilot-package-certification",
        status,
        package_status: manifest?.package_status ?? null,
        manifest_path: PILOT_MANIFEST_PATH,
        checks: [
            { id: "manifest_schema", status: findings.some((item) => item.code.startsWith("MANIFEST_") || item.code === "SCHEMA_VERSION_INVALID" || item.code === "PACKAGE_STATUS_INVALID") ? "fail" : "pass" },
            { id: "referenced_assets", status: findings.some((item) => item.code.startsWith("ASSET")) ? "fail" : "pass" },
            { id: "metric_contract", status: findings.some((item) => item.code.startsWith("METRIC") || item.code.startsWith("ATTRIBUTION") || item.code.startsWith("REVENUE") || item.code.startsWith("VERIFIED")) ? "fail" : "pass" },
            { id: "claims_boundary", status: findings.some((item) => item.code.startsWith("CLAIM") || item.code.startsWith("TOPOLOGY")) ? "fail" : "pass" },
            { id: "template_integrity", status: templateIntegrity.status },
        ],
        findings,
        template_integrity: {
            status: templateIntegrity.status,
            files: templateIntegrity.files,
        },
        attribution_stages: attribution.stages,
        verified_authorities: attribution.verifiedAuthorities,
        external_pending: externalPending,
        external_pending_count: externalPending.length,
        safety: {
            database_mutations: false,
            external_provider_calls: false,
            writes: false,
            unsupported_claims_promoted: unsupportedClaimsPromoted,
            template_drift: templateDrift,
        },
    };
}

function parseArguments(argv) {
    const options = { root: ".", json: false, strict: false, help: false };
    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];
        if (argument === "--json") options.json = true;
        else if (argument === "--strict") options.strict = true;
        else if (argument === "--help" || argument === "-h") options.help = true;
        else if (argument === "--root") options.root = argv[++index] ?? "";
        else if (argument.startsWith("--root=")) options.root = argument.slice("--root=".length);
        else throw new Error(`Unknown argument: ${argument}`);
    }

    return options;
}

function printUsage() {
    console.log("Usage: node scripts/qa/certify-dovra-pilot-package.mjs --root <repository-root> [--json] [--strict]");
    console.log("Certifies pilot package integrity only; it never contacts providers or mutates data.");
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url))) {
    try {
        const options = parseArguments(process.argv.slice(2));
        if (options.help) {
            printUsage();
            process.exit(0);
        }
        const report = certifyPilotPackage(resolve(process.cwd(), options.root));
        const finalStatus = report.status === "FAIL" ? "FAIL" : options.strict ? "NO_GO" : "PASS";
        const output = { ...report, status: finalStatus, strict: options.strict };
        if (options.json) {
            console.log(JSON.stringify(output, null, 2));
        } else {
            console.log(`status: ${output.status}`);
            console.log(`checks: ${output.checks.length}`);
            console.log(`findings: ${output.findings.length}`);
            console.log(`external_pending: ${output.external_pending_count}`);
            for (const item of output.findings) console.log(`[${item.code}] ${item.path}: ${item.detail}`);
        }
        process.exitCode = output.status === "PASS" ? 0 : 1;
    } catch (error) {
        if (process.argv.includes("--json")) {
            console.log(JSON.stringify({
                schema_version: PILOT_PACKAGE_SCHEMA_VERSION,
                kind: "dovra-pilot-package-certification",
                status: "FAIL",
                findings: [{ code: "CLI_INVALID", path: "", detail: error instanceof Error ? error.message : String(error) }],
                external_pending: externalPending,
                external_pending_count: externalPending.length,
                safety: { database_mutations: false, external_provider_calls: false, writes: false, unsupported_claims_promoted: false, template_drift: false },
            }, null, 2));
        } else {
            console.error(error instanceof Error ? error.message : String(error));
        }
        process.exitCode = 1;
    }
}
