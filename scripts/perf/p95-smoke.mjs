import { performance } from "node:perf_hooks";

const baseUrl = (process.env.PERF_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
const iterations = Number(process.env.PERF_ITERATIONS ?? 20);
const maxP95Ms = Number(process.env.PERF_MAX_P95_MS ?? 500);
const paths = (process.env.PERF_PATHS ?? "/up,/").split(",").map((path) => path.trim());

for (const path of paths) {
    const samples = [];
    for (let index = 0; index < iterations; index += 1) {
        const started = performance.now();
        const response = await fetch(`${baseUrl}${path}`, { redirect: "manual" });
        samples.push(performance.now() - started);
        if (response.status >= 500) {
            throw new Error(`${path} returned HTTP ${response.status}.`);
        }
    }

    samples.sort((a, b) => a - b);
    const p95 = samples[Math.ceil(samples.length * 0.95) - 1];
    console.log(`${path}: p95 ${p95.toFixed(1)} ms (${iterations} samples)`);
    if (p95 > maxP95Ms) {
        throw new Error(`${path} exceeded ${maxP95Ms} ms p95 budget.`);
    }
}
