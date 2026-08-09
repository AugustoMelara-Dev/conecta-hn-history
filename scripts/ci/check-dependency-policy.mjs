import fs from 'node:fs';

const manifest = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const lockfile = JSON.parse(fs.readFileSync('package-lock.json', 'utf8'));
const workflow = fs.readFileSync('.github/workflows/ci.yml', 'utf8');
const dockerfile = fs.readFileSync('Dockerfile', 'utf8');
const failures = [];

function expect(condition, message) {
    if (!condition) failures.push(message);
}

for (const dependency of ['react', 'react-dom']) {
    const declared = manifest.dependencies?.[dependency];
    expect(typeof declared === 'string' && declared.includes('18.3'), `${dependency} must remain on the supported React 18.3 line`);
    expect(manifest.devDependencies?.[dependency] === undefined, `${dependency} must not be duplicated in devDependencies`);
    expect(lockfile.packages?.['']?.dependencies?.[dependency] === declared, `${dependency} lockfile declaration must match package.json`);
}

expect(manifest.engines?.node === '22.x', 'package.json must pin the supported Node 22 LTS major');
expect(lockfile.packages?.['']?.engines?.node === '22.x', 'package-lock.json must preserve the Node 22 LTS engine');
expect(dockerfile.includes('node:22.13-bookworm-slim'), 'Docker must use the supported Node 22.13 build image');
expect(workflow.includes('node-version: "22.13"'), 'CI must use Node 22.13');

if (failures.length > 0) {
    console.error('Frontend dependency policy failed:');
    failures.forEach((failure) => console.error(`- ${failure}`));
    process.exit(1);
}

console.log('Frontend dependency policy passed.');
