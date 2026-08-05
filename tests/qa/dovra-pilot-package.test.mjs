import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import {
    copyFileSync,
    existsSync,
    lstatSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    readdirSync,
    rmSync,
    symlinkSync,
    unlinkSync,
    writeFileSync,
} from "node:fs";
import { dirname, join, relative, resolve } from "node:path";
import { tmpdir } from "node:os";

const repositoryRoot = resolve(import.meta.dirname, "../..");
const certifier = join(repositoryRoot, "scripts/qa/certify-dovra-pilot-package.mjs");
const manifestRelativePath = "docs/dovra/pilot/dovra-program13/pilot-analytics-manifest.json";
const readinessRelativePath = "docs/dovra/pilot/dovra-program13/pilot-readiness-package.md";
const manifestPath = join(repositoryRoot, manifestRelativePath);

function runCertifier(root, extraArguments = []) {
    return spawnSync(process.execPath, [certifier, "--root", root, "--json", ...extraArguments], {
        cwd: repositoryRoot,
        encoding: "utf8",
        shell: false,
    });
}

function parseReport(result) {
    assert.ok(result.stdout.trim(), `expected JSON output; stderr=${result.stderr}`);

    return JSON.parse(result.stdout);
}

function writeJson(path, value) {
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`);
}

function copyManifestAndAssets() {
    const fixtureRoot = mkdtempSync(join(tmpdir(), "dovra-pilot-package-"));
    const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));

    writeJson(join(fixtureRoot, manifestRelativePath), manifest);
    const readinessTarget = join(fixtureRoot, readinessRelativePath);
    mkdirSync(dirname(readinessTarget), { recursive: true });
    copyFileSync(join(repositoryRoot, readinessRelativePath), readinessTarget);
    for (const asset of manifest.referenced_assets) {
        const source = join(repositoryRoot, asset.path);
        const target = join(fixtureRoot, asset.path);
        mkdirSync(dirname(target), { recursive: true });
        copyFileSync(source, target);
    }

    return { fixtureRoot, manifest, manifestPath: join(fixtureRoot, manifestRelativePath) };
}

function snapshotFiles(root) {
    const entries = [];
    const visit = (directory) => {
        for (const entry of readdirSync(directory, { withFileTypes: true })) {
            const path = join(directory, entry.name);
            if (entry.isDirectory()) {
                visit(path);
            } else {
                entries.push([
                    relative(root, path).replaceAll("\\", "/"),
                    readFileSync(path),
                    lstatSync(path).mode,
                ]);
            }
        }
    };
    visit(root);
    return entries.sort(([left], [right]) => left.localeCompare(right));
}

function mutateManifest(fixture, mutate) {
    mutate(fixture.manifest);
    writeJson(fixture.manifestPath, fixture.manifest);
}

function assertFailingPackage(fixture, expectedCode) {
    const result = runCertifier(fixture.fixtureRoot);
    const report = parseReport(result);

    assert.notEqual(result.status, 0, result.stdout);
    assert.equal(report.status, "FAIL");
    assert.ok(
        report.findings.some((finding) => finding.code === expectedCode),
        `${expectedCode} missing from ${JSON.stringify(report.findings)}`,
    );
}

test("pilot package and certifier paths exist", () => {
    assert.equal(existsSync(certifier), true, "pilot package certifier is missing");
    assert.equal(existsSync(manifestPath), true, "pilot analytics manifest is missing");
});

test("certifier is deterministic, read-only, and reports external pending inputs", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    const before = snapshotFiles(fixture.fixtureRoot);

    const first = runCertifier(fixture.fixtureRoot);
    const second = runCertifier(fixture.fixtureRoot);
    const firstReport = parseReport(first);
    const secondReport = parseReport(second);

    assert.equal(first.status, 0, first.stderr || first.stdout);
    assert.equal(second.status, 0, second.stderr || second.stdout);
    assert.equal(first.stdout, second.stdout);
    assert.equal(firstReport.status, "PASS");
    assert.equal(firstReport.package_status, "repository_template");
    assert.ok(firstReport.external_pending_count > 0);
    assert.equal(firstReport.template_integrity.status, "pass");
    assert.ok(firstReport.template_integrity.files.every((file) => file.expected_sha256 === file.observed_sha256));
    assert.deepEqual(snapshotFiles(fixture.fixtureRoot), before);
});

test("strict mode remains NO_GO while the repository template has external pending inputs", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));

    const result = runCertifier(fixture.fixtureRoot, ["--strict"]);
    const report = parseReport(result);

    assert.notEqual(result.status, 0);
    assert.equal(report.status, "NO_GO");
    assert.ok(report.external_pending_count > 0);
});

test("rejects a missing referenced asset", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    unlinkSync(join(fixture.fixtureRoot, fixture.manifest.referenced_assets[0].path));

    assertFailingPackage(fixture, "ASSET_MISSING");
});

test("rejects absolute and out-of-root asset references", (t) => {
    for (const candidate of ["../outside.txt", "C:\\outside.txt", "/outside.txt"]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            manifest.referenced_assets[0].path = candidate;
        });

        assertFailingPackage(fixture, "ASSET_PATH_INVALID");
    }
});

test("rejects a symlinked or junction asset that escapes the package root", (t) => {
    const fixture = copyManifestAndAssets();
    const victimRoot = mkdtempSync(join(tmpdir(), "dovra-pilot-victim-"));
    const linkedDirectory = join(fixture.fixtureRoot, "linked-assets");
    const linkedAsset = "linked-assets/demo-script.md";
    writeFileSync(join(victimRoot, "demo-script.md"), "outside package root\n");
    mutateManifest(fixture, (manifest) => {
        manifest.referenced_assets[0].path = linkedAsset;
    });
    try {
        symlinkSync(victimRoot, linkedDirectory, "junction");
    } catch (error) {
        t.after(() => {
            rmSync(fixture.fixtureRoot, { recursive: true, force: true });
            rmSync(victimRoot, { recursive: true, force: true });
        });
        t.skip(`directory junctions unavailable: ${error instanceof Error ? error.message : String(error)}`);
        return;
    }
    t.after(() => {
        rmSync(fixture.fixtureRoot, { recursive: true, force: true });
        rmSync(victimRoot, { recursive: true, force: true });
    });

    assertFailingPackage(fixture, "ASSET_SYMLINK");
});

test("rejects duplicate JSON keys", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    const source = readFileSync(fixture.manifestPath, "utf8");
    writeFileSync(fixture.manifestPath, source.replace('"schema_version": 1,', '"schema_version": 1,\n  "schema_version": 1,'));

    assertFailingPackage(fixture, "MANIFEST_JSON_INVALID");
});

test("rejects every missing metric contract field", (t) => {
    const fields = ["id", "name", "category", "authority", "source", "window", "numerator", "denominator", "value", "status", "evidence"];

    for (const field of fields) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            delete manifest.metrics[0][field];
        });

        assertFailingPackage(fixture, "METRIC_FIELD_MISSING");
    }
});

test("rejects non-null metric values without evidence id and URI", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    mutateManifest(fixture, (manifest) => {
        manifest.metrics[0].value = 1;
    });

    assertFailingPackage(fixture, "METRIC_VALUE_FORBIDDEN");
});

test("repository_template rejects non-null values even with complete-looking evidence", (t) => {
    for (const uri of ["https://analytics.example.invalid/export.json", "javascript:alert(1)", "data:text/plain,metric", "file:///tmp/metric.json", "evidence/metric.json", "../outside/metric.json"]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            manifest.metrics[0].value = 1;
            manifest.metrics[0].evidence = { id: "future-evidence", uri };
        });

        assertFailingPackage(fixture, "METRIC_VALUE_FORBIDDEN");
    }
});

test("rejects affirmative commercial claims in metric text", (t) => {
    for (const [field, value] of [
        ["name", "Observed 4 active customers in a live pilot"],
        ["source", "Observed revenue of $500 MRR from current pilot"],
        ["numerator", "3 customers completed the pilot and demonstrated traction"],
        ["name", "Only 4 active customers in a live pilot"],
        ["source", "External evidence confirms 4 active customers in a live pilot"],
        ["numerator", "Revenue remains $500 MRR from the current pilot"],
    ]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            manifest.metrics[0][field] = value;
        });

        assertFailingPackage(fixture, "CLAIM_TEXT_INVALID");
    }
});

test("rejects affirmative commercial claims injected into pilot readiness text", (t) => {
    for (const [text, shouldFail] of [
        ["Observed 4 active customers and $120 MRR in a live pilot.\n", true],
        ["This is not a claim; Observed 4 active customers and $120 MRR in a live pilot.\n", true],
        ["Dovra has only 10 active customers in a live pilot.\n", true],
        ["3 participants completed the pilot and demonstrated traction.\n", true],
    ]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        writeFileSync(join(fixture.fixtureRoot, readinessRelativePath), text, { flag: "a" });

        const result = runCertifier(fixture.fixtureRoot);
        const report = parseReport(result);
        if (shouldFail) {
            assert.notEqual(result.status, 0, result.stdout);
            assert.equal(report.status, "FAIL");
            assert.ok(report.findings.some((finding) => finding.code === "CLAIM_TEXT_INVALID"), `${text} missing CLAIM_TEXT_INVALID`);
        } else {
            assert.equal(result.status, 0, result.stdout);
            assert.equal(report.status, "PASS");
        }
    }
});

test("rejects commercial claim phrasing that lexical patterns may miss", (t) => {
    for (const text of [
        "Dovra serves 4 paying customers in production.\n",
        "There are 4 customers using Dovra in production.\n",
        "Current customer count is 4.\n",
        "This is not a claim, observed 4 paying customers in production.\n",
        "Future pilot example observed 4 paying customers in production.\n",
    ]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        writeFileSync(join(fixture.fixtureRoot, readinessRelativePath), text, { flag: "a" });

        const result = runCertifier(fixture.fixtureRoot);
        const report = parseReport(result);
        assert.notEqual(result.status, 0, result.stdout);
        assert.equal(report.status, "FAIL");
        assert.ok(report.findings.some((finding) => ["CLAIM_TEXT_INVALID", "TEMPLATE_DRIFT"].includes(finding.code)), `${text} was not rejected by a claim or template boundary`);
        assert.equal(report.safety.template_drift, true);
        assert.equal(report.safety.unsupported_claims_promoted, false);
    }
});

test("rejects unreviewed template drift", (t) => {
    for (const mutate of [
        (fixture) => mutateManifest(fixture, (manifest) => {
            manifest.metrics[0].name = "Activation rate (unreviewed copy)";
            writeJson(fixture.manifestPath, fixture.manifest);
        }),
        (fixture) => writeFileSync(join(fixture.fixtureRoot, readinessRelativePath), "Reviewer note: local fixture only.\n", { flag: "a" }),
    ]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutate(fixture);

        const result = runCertifier(fixture.fixtureRoot);
        const report = parseReport(result);
        assert.notEqual(result.status, 0, result.stdout);
        assert.equal(report.status, "FAIL");
        assert.ok(report.findings.some((finding) => finding.code === "TEMPLATE_DRIFT"));
        assert.equal(report.safety.template_drift, true);
        assert.equal(report.safety.unsupported_claims_promoted, false);
    }
});

test("accepts CRLF-normalized copies of the reviewed template", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    for (const path of [fixture.manifestPath, join(fixture.fixtureRoot, readinessRelativePath)]) {
        const source = readFileSync(path, "utf8");
        writeFileSync(path, source.replaceAll("\n", "\r\n"));
    }

    const result = runCertifier(fixture.fixtureRoot);
    const report = parseReport(result);
    assert.equal(result.status, 0, result.stdout);
    assert.equal(report.status, "PASS");
    assert.equal(report.template_integrity.status, "pass");
});

test("rejects active pilot, customer, traction, revenue, PMF, or investment claims", (t) => {
    for (const claim of ["active_pilot", "customers", "traction", "revenue", "pmf", "investment_ready"]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            manifest.claims_boundary[claim] = true;
        });

        assertFailingPackage(fixture, "CLAIM_BOUNDARY_INVALID");
    }
});

test("rejects promotion of prepared or influenced attribution into verified", (t) => {
    for (const stage of ["prepared", "influenced"]) {
        const fixture = copyManifestAndAssets();
        t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
        mutateManifest(fixture, (manifest) => {
            const metric = manifest.metrics.find((candidate) => candidate.attribution_stage === stage);
            metric.authority = "verified_commercial_outcomes";
        });

        assertFailingPackage(fixture, "ATTRIBUTION_AUTHORITY_INVALID");
    }
});

test("rejects symlinked or junctioned manifest topology before reading it", (t) => {
    const fixture = copyManifestAndAssets();
    const victimRoot = mkdtempSync(join(tmpdir(), "dovra-pilot-manifest-victim-"));
    const parentDirectory = join(fixture.fixtureRoot, "docs/dovra/pilot");
    const manifestDirectory = join(parentDirectory, "dovra-program13");
    const victimManifestDirectory = join(victimRoot, "dovra-program13");
    mkdirSync(victimManifestDirectory, { recursive: true });
    writeFileSync(join(victimManifestDirectory, "pilot-analytics-manifest.json"), "{\"schema_version\":1}\n");
    rmSync(manifestDirectory, { recursive: true, force: true });
    try {
        symlinkSync(victimManifestDirectory, manifestDirectory, "junction");
    } catch (error) {
        t.after(() => {
            rmSync(fixture.fixtureRoot, { recursive: true, force: true });
            rmSync(victimRoot, { recursive: true, force: true });
        });
        t.skip(`directory junctions unavailable: ${error instanceof Error ? error.message : String(error)}`);
        return;
    }
    t.after(() => {
        rmSync(fixture.fixtureRoot, { recursive: true, force: true });
        rmSync(victimRoot, { recursive: true, force: true });
    });

    assertFailingPackage(fixture, "TOPOLOGY_LINK");
});

test("rejects a junction root before reading the manifest", (t) => {
    const fixture = copyManifestAndAssets();
    const parent = mkdtempSync(join(tmpdir(), "dovra-pilot-root-parent-"));
    const linkedRoot = join(parent, "root-junction");
    try {
        symlinkSync(fixture.fixtureRoot, linkedRoot, "junction");
    } catch (error) {
        t.after(() => {
            rmSync(fixture.fixtureRoot, { recursive: true, force: true });
            rmSync(parent, { recursive: true, force: true });
        });
        t.skip(`directory junctions unavailable: ${error instanceof Error ? error.message : String(error)}`);
        return;
    }
    t.after(() => {
        rmSync(fixture.fixtureRoot, { recursive: true, force: true });
        rmSync(parent, { recursive: true, force: true });
    });

    assertFailingPackage({ fixtureRoot: linkedRoot }, "TOPOLOGY_LINK");
});

test("strict schema keeps prepared, influenced, and verified authorities separate", (t) => {
    const fixture = copyManifestAndAssets();
    t.after(() => rmSync(fixture.fixtureRoot, { recursive: true, force: true }));
    const report = parseReport(runCertifier(fixture.fixtureRoot));

    assert.equal(report.status, "PASS");
    assert.deepEqual(report.attribution_stages.sort(), ["influenced", "prepared", "verified"]);
    assert.deepEqual(report.verified_authorities.sort(), ["order_paid", "order_refunded", "verified_commercial_outcomes"]);
});
