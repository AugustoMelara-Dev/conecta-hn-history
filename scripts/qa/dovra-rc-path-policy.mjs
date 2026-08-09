const excludedFirstSegments = new Set([
    ".git",
    ".worktrees",
    "node_modules",
    "storage",
    "vendor",
    "secrets",
    "keys",
    "private-keys",
    "credentials",
]);

const generatedFirstSegments = new Set([
    "build",
    "coverage",
    "dist",
    "out",
    "playwright-report",
    "reports",
    "results",
    "test-results",
]);

const sensitiveBasenames = new Set([
    "secret",
    "secrets",
    "credentials",
    "credential",
    "passwords",
    "password",
    "id_rsa",
    "id_dsa",
    "id_ed25519",
]);

export function releaseCandidatePathExclusion(path) {
    const segments = String(path)
        .replaceAll("\\", "/")
        .split("/")
        .filter(Boolean)
        .map((segment) => segment.toLowerCase());
    const first = segments[0] ?? "";
    const basename = segments.at(-1) ?? "";

    if (basename === ".env" || (basename.startsWith(".env.") && basename !== ".env.example")) {
        return "environment-secret";
    }
    if (excludedFirstSegments.has(first)) {
        return "sensitive-or-runtime-directory";
    }
    if (first === "artifacts" && segments[1] === "releases") {
        return "release-artifact";
    }
    if (generatedFirstSegments.has(first)) {
        return "generated-output";
    }
    if (sensitiveBasenames.has(basename)
        || /\.(?:pem|key|p12|pfx|jks|keystore|secret|secrets|credentials)$/u.test(basename)) {
        return "secret-or-private-key";
    }
    return null;
}
