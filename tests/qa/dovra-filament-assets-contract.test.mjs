import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "../..");

test("release installs publish the Filament CSS required by routed panels", () => {
    const composer = JSON.parse(readFileSync(resolve(root, "composer.json"), "utf8"));
    const postAutoload = composer?.scripts?.["post-autoload-dump"];
    const dockerfile = readFileSync(resolve(root, "Dockerfile"), "utf8");

    assert.ok(Array.isArray(postAutoload));
    assert.ok(postAutoload.includes("@php artisan filament:assets --ansi"));
    assert.match(dockerfile, /composer install/u);
    assert.doesNotMatch(dockerfile, /composer install[\s\S]{0,240}--no-scripts/u);

    for (const relativePath of [
        "vendor/filament/forms/dist/index.css",
        "vendor/filament/support/dist/index.css",
        "public/css/filament/forms/forms.css",
        "public/css/filament/support/support.css",
    ]) {
        assert.equal(existsSync(resolve(root, relativePath)), true, `${relativePath} must exist after filament:assets`);
    }
});
