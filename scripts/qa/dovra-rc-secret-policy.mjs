function normalizeAssignmentName(name) {
    return name
        .replace(/([A-Z]+)([A-Z][a-z])/gu, "$1_$2")
        .replace(/([a-z0-9])([A-Z])/gu, "$1_$2")
        .replace(/[^A-Za-z0-9]+/gu, "_")
        .replace(/^_+|_+$/gu, "")
        .toUpperCase();
}

function isSensitiveAssignmentName(name) {
    const parts = normalizeAssignmentName(name).split("_").filter(Boolean);
    if (parts.includes("SECRET") || parts.includes("TOKEN") || parts.includes("PASSWORD")) {
        return true;
    }
    for (let index = 0; index < parts.length - 1; index += 1) {
        if (["API_KEY", "PRIVATE_KEY", "ACCESS_KEY"].includes(`${parts[index]}_${parts[index + 1]}`)) {
            return true;
        }
    }
    return false;
}

function staticAssignmentValue(rawValue, delimiter, configSurface) {
    const candidate = rawValue.trim();
    if (candidate.length === 0) {
        return "";
    }
    const quote = candidate[0];
    let value;
    if (quote === "\"" || quote === "'") {
        let escaped = false;
        let closingIndex = -1;
        for (let index = 1; index < candidate.length; index += 1) {
            const character = candidate[index];
            if (!escaped && character === quote) {
                closingIndex = index;
                break;
            }
            if (!escaped && character === "\\") {
                escaped = true;
            } else {
                escaped = false;
            }
        }
        if (closingIndex < 0 || !/^\s*[,;]?\s*(?:(?:#|\/\/).*)?$/u.test(candidate.slice(closingIndex + 1))) {
            return null;
        }
        value = candidate.slice(1, closingIndex);
    } else {
        if (!configSurface || delimiter === "=>") {
            return null;
        }
        value = candidate.replace(/\s+(?:#|\/\/).*$/u, "").replace(/[,;]\s*$/u, "").trim();
        if (value.length === 0) {
            return "";
        }
        if (/\s/u.test(value)) {
            return null;
        }
    }
    if (/\$|\{\{|\}\}|\(|\)|::|->|=>|\[|\]|\{|\}/u.test(value)
        || /^(?:env|getenv)\b|^process\.env\b/iu.test(value)
        || /^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+$/u.test(value)) {
        return null;
    }
    return value;
}

function isKnownLocalDatabaseSentinel(name, value) {
    const sentinels = new Map([
        ["PGPASSWORD", new Set(["postgres"])],
        ["POSTGRES_PASSWORD", new Set(["postgres"])],
        ["DB_PASSWORD", new Set(["postgres", "rls-test-password"])],
        ["DB_PLATFORM_PASSWORD", new Set(["rls-platform-password"])],
        ["MYSQL_PASSWORD", new Set(["conecta"])],
        ["MYSQL_ROOT_PASSWORD", new Set(["root"])],
    ]);
    return sentinels.get(name)?.has(value) ?? false;
}

function isCredentialConfigSurface(path) {
    const normalized = path.replaceAll("\\", "/").toLowerCase();
    return normalized.endsWith("/.env.example")
        || normalized === ".env.example"
        || /\.(?:ya?ml|toml|ini|properties)$/u.test(normalized);
}

function shannonEntropy(value) {
    const counts = new Map();
    for (const character of value) {
        counts.set(character, (counts.get(character) ?? 0) + 1);
    }
    let entropy = 0;
    for (const count of counts.values()) {
        const probability = count / value.length;
        entropy -= probability * Math.log2(probability);
    }
    return entropy;
}

function characterClassCount(value) {
    return [/[a-z]/u, /[A-Z]/u, /[0-9]/u, /[^A-Za-z0-9]/u]
        .filter((pattern) => pattern.test(value))
        .length;
}

function isHighEntropyCredential(value) {
    return value.length >= 24
        && !/\p{White_Space}/u.test(value)
        && characterClassCount(value) >= 2
        && shannonEntropy(value) >= 3.5;
}

function isFileLocatorLiteral(value) {
    if (/[\p{White_Space}\p{Cc}]/u.test(value) || /[?#]/u.test(value)) {
        return false;
    }
    const locator = value.replace(/\\\\/gu, "\\");
    if (!/[\\/]/u.test(locator)) {
        return false;
    }
    let remainder = locator;
    const drivePrefix = remainder.match(/^[A-Za-z]:[\\/]/u);
    if (drivePrefix) {
        remainder = remainder.slice(drivePrefix[0].length);
    } else if (/^[A-Za-z][A-Za-z0-9+.-]*:/u.test(remainder)) {
        return false;
    } else if (/^\.\.[\\/]/u.test(remainder)) {
        remainder = remainder.slice(3);
    } else if (/^\.[\\/]/u.test(remainder)) {
        remainder = remainder.slice(2);
    } else if (/^[\\/]/u.test(remainder)) {
        remainder = remainder.slice(1);
    }
    const segments = remainder.split(/[\\/]/u);
    if (segments.length === 0 || segments.some((segment) => segment.length === 0
        || segment === "."
        || segment === ".."
        || !/^[A-Za-z0-9._-]+$/u.test(segment))) {
        return false;
    }
    const basename = segments.at(-1);
    const extension = basename.match(/^(.+)\.([A-Za-z0-9]{1,10})$/u);
    return extension !== null && extension[1].length > 0;
}

function hasProductionEntropy(value) {
    return characterClassCount(value) >= 2 && shannonEntropy(value) >= 3.5;
}

function lineNumberAt(text, index) {
    return text.slice(0, index).split("\n").length;
}

function decodesJsonObject(value) {
    try {
        const decoded = JSON.parse(Buffer.from(value, "base64url").toString("utf8"));
        return decoded !== null && typeof decoded === "object" && !Array.isArray(decoded);
    } catch {
        return false;
    }
}

function isKnownProviderExample(value) {
    return value === "AKIAIOSFODNN7EXAMPLE" || value === "whsec_dummy_secret_for_local_dev_only";
}

function valueSignatureDetections(text) {
    const detections = [];
    const signatures = [
        ["aws-access-key", /\b(?:AKIA|ASIA)([A-Z0-9]{16})\b/gu],
        ["github-token", /\b(?:gh[pousr]_([A-Za-z0-9]{20,})|github_pat_([A-Za-z0-9_]{20,}))\b/gu],
        ["openai-key", /\bsk-(proj-)?([A-Za-z0-9_-]+)\b/gu],
        ["stripe-key", /\b(sk_live_|rk_live_|whsec_)([A-Za-z0-9_-]+)\b/gu],
        ["slack-token", /\bxox[a-z]-([A-Za-z0-9-]{16,})\b/giu],
        ["google-api-key", /\bAIza([0-9A-Za-z_-]{35})\b/gu],
    ];
    for (const [type, pattern] of signatures) {
        for (const match of text.matchAll(pattern)) {
            const full = match[0];
            if (isKnownProviderExample(full)) {
                continue;
            }
            let body = match.at(-1);
            let minimum = 0;
            if (type === "aws-access-key") minimum = 16;
            if (type === "github-token") body = match[1] ?? match[2];
            if (type === "openai-key") minimum = match[1] === "proj-" ? 40 : 32;
            if (type === "stripe-key") minimum = match[1] === "whsec_" ? 32 : 24;
            if (type === "slack-token") minimum = 20;
            if (type === "google-api-key") minimum = 35;
            if (body.length >= minimum && hasProductionEntropy(body)) {
                detections.push({ line: lineNumberAt(text, match.index), type });
            }
        }
    }
    const jwtPattern = /\b([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\b/gu;
    for (const match of text.matchAll(jwtPattern)) {
        if (match[3].length >= 32 && hasProductionEntropy(match[3]) && decodesJsonObject(match[1]) && decodesJsonObject(match[2])) {
            detections.push({ line: lineNumberAt(text, match.index), type: "jwt" });
        }
    }
    const pemPattern = /-----BEGIN ([A-Z0-9 ]*PRIVATE KEY)-----([\s\S]*?)-----END \1-----/gu;
    for (const match of text.matchAll(pemPattern)) {
        const material = match[2].replace(/\s+/gu, "");
        if (material.length >= 64 && /^[A-Za-z0-9+/=]+$/u.test(material)) {
            detections.push({ line: lineNumberAt(text, match.index), type: "pem-private-key" });
        }
    }
    return detections;
}

function isConfigLiteralExempt(name, value) {
    if (value.length === 0 || /^(?:null|none)$/iu.test(value)) {
        return true;
    }
    if (name === "MYSQL_ALLOW_EMPTY_PASSWORD" || isKnownLocalDatabaseSentinel(name, value) || isKnownProviderExample(value)) {
        return true;
    }
    if (/(?:_REFERENCE|_REF)$/u.test(name)) {
        return true;
    }
    return /(?:_(?:TTL|TIMEOUT)(?:_(?:DAYS|HOURS|MINUTES|SECONDS))?|_(?:DAYS|HOURS|MINUTES|SECONDS))$/u.test(name)
        && /^\d+(?:\.\d+)?$/u.test(value);
}

export function secretSignatureDetections(content, relativePath) {
    const text = Buffer.isBuffer(content) ? content.toString("utf8") : String(content);
    const detections = valueSignatureDetections(text);
    const configSurface = isCredentialConfigSurface(relativePath);
    for (const [index, line] of text.split(/\r?\n/u).entries()) {
        const match = line.match(/^[ \t]*(?:(?:export[ \t]+)?(?:const|let|var)[ \t]+)?(?:(['"])([A-Za-z_][A-Za-z0-9_]*)\1|([A-Za-z_][A-Za-z0-9_]*))[ \t]*(=>|:|=)[ \t]*(.*)$/u);
        if (!match) {
            continue;
        }
        const name = normalizeAssignmentName(match[2] ?? match[3]);
        if (!isSensitiveAssignmentName(name)) {
            continue;
        }
        const value = staticAssignmentValue(match[5], match[4], configSurface);
        if (value === null || (configSurface && isConfigLiteralExempt(name, value))) {
            continue;
        }
        const quotedName = match[1] !== undefined;
        if (configSurface || (!quotedName && isHighEntropyCredential(value) && !isFileLocatorLiteral(value))) {
            detections.push({ line: index + 1, type: "sensitive-literal" });
        }
    }
    return detections.filter((detection, index, all) => all.findIndex((candidate) => candidate.line === detection.line && candidate.type === detection.type) === index);
}
