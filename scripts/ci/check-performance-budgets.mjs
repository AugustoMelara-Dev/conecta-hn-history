import { readFile, stat } from "node:fs/promises";
import path from "node:path";

const root = process.cwd();
const manifestPath = path.join(root, "public", "build", "manifest.json");
const buildRoot = path.dirname(manifestPath);

const budgets = {
    entryJavaScript: 400 * 1024,
    entryCss: 200 * 1024,
    lazyChunk: 160 * 1024,
    totalJavaScript: 1200 * 1024,
    totalCss: 220 * 1024,
};

const manifest = JSON.parse(await readFile(manifestPath, "utf8"));
const files = new Map();

for (const item of Object.values(manifest)) {
    if (item.file) files.set(item.file, await sizeOf(item.file));
    for (const css of item.css ?? []) files.set(css, await sizeOf(css));
}

const failures = [];
let totalJavaScript = 0;
let totalCss = 0;

for (const [file, bytes] of files) {
    if (file.endsWith(".js")) totalJavaScript += bytes;
    if (file.endsWith(".css")) totalCss += bytes;
}

for (const [key, item] of Object.entries(manifest)) {
    if (!item.isEntry || !item.file) continue;
    check(`${key} entry JavaScript`, files.get(item.file), budgets.entryJavaScript);
    for (const css of item.css ?? []) {
        check(`${key} entry CSS`, files.get(css), budgets.entryCss);
    }
}

for (const [key, item] of Object.entries(manifest)) {
    if (item.isEntry || !item.file?.endsWith(".js")) continue;
    check(`${key} lazy chunk`, files.get(item.file), budgets.lazyChunk);
}

check("total JavaScript", totalJavaScript, budgets.totalJavaScript);
check("total CSS", totalCss, budgets.totalCss);

if (failures.length > 0) {
    console.error("Performance budget failed:");
    for (const failure of failures) console.error(`- ${failure}`);
    process.exit(1);
}

console.log(
    `Performance budgets passed: ${format(totalJavaScript)} JS, ${format(totalCss)} CSS, ${files.size} emitted assets checked.`,
);

async function sizeOf(relativePath) {
    return (await stat(path.join(buildRoot, relativePath))).size;
}

function check(label, actual, limit) {
    if (actual > limit) {
        failures.push(`${label}: ${format(actual)} exceeds ${format(limit)}`);
    }
}

function format(bytes) {
    return `${(bytes / 1024).toFixed(1)} KiB`;
}
