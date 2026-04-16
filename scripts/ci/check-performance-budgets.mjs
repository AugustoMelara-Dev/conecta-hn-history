import fs from 'node:fs';
import path from 'node:path';

const manifestPath = path.resolve('public/build/manifest.json');
// Baseline budgets calibrated against current production bundle sizes.
// Tighten these progressively as chunk-splitting optimizations land.
const maxJsKb = 430;
const maxCssKb = 150;

if (!fs.existsSync(manifestPath)) {
  console.error(`Performance budget failed: missing manifest at ${manifestPath}`);
  process.exit(1);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const entries = Object.values(manifest);
const failures = [];

for (const entry of entries) {
  if (entry.file) {
    const filePath = path.resolve('public/build', entry.file);
    if (fs.existsSync(filePath)) {
      const kb = fs.statSync(filePath).size / 1024;
      if (entry.file.endsWith('.js') && kb > maxJsKb) {
        failures.push(`${entry.file} is ${kb.toFixed(1)}KB (max ${maxJsKb}KB)`);
      }
    }
  }

  for (const cssFile of entry.css ?? []) {
    const filePath = path.resolve('public/build', cssFile);
    if (!fs.existsSync(filePath)) {
      continue;
    }

    const kb = fs.statSync(filePath).size / 1024;
    if (kb > maxCssKb) {
      failures.push(`${cssFile} is ${kb.toFixed(1)}KB (max ${maxCssKb}KB)`);
    }
  }
}

if (failures.length > 0) {
  console.error('Performance budget failed:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('Performance budget passed.');
