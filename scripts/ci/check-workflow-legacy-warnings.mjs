import fs from 'node:fs/promises';
import path from 'node:path';

const workflowsDir = path.resolve('.github', 'workflows');

const rules = [
  {
    name: 'Deprecated workflow command set-output',
    regex: /::set-output\s+name=/g,
    recommendation: 'Use $GITHUB_OUTPUT instead of ::set-output.',
  },
  {
    name: 'Deprecated workflow command save-state',
    regex: /::save-state\s+name=/g,
    recommendation: 'Use $GITHUB_STATE instead of ::save-state.',
  },
  {
    name: 'Legacy checkout action',
    regex: /uses:\s*actions\/checkout@v[1-3]\b/g,
    recommendation: 'Upgrade to actions/checkout@v4.',
  },
  {
    name: 'Legacy setup-node action',
    regex: /uses:\s*actions\/setup-node@v[1-3]\b/g,
    recommendation: 'Upgrade to actions/setup-node@v4.',
  },
  {
    name: 'Legacy artifact upload action',
    regex: /uses:\s*actions\/upload-artifact@v[1-3]\b/g,
    recommendation: 'Upgrade to actions/upload-artifact@v4.',
  },
  {
    name: 'Legacy artifact download action',
    regex: /uses:\s*actions\/download-artifact@v[1-3]\b/g,
    recommendation: 'Upgrade to actions/download-artifact@v4.',
  },
  {
    name: 'Legacy ubuntu runner pinned',
    regex: /runs-on:\s*ubuntu-20\.04\b/g,
    recommendation: 'Use ubuntu-latest unless pinning is strictly required.',
  },
];

function findLine(content, index) {
  return content.slice(0, index).split('\n').length;
}

async function main() {
  const files = (await fs.readdir(workflowsDir))
    .filter((name) => name.endsWith('.yml') || name.endsWith('.yaml'))
    .map((name) => path.join(workflowsDir, name));

  const findings = [];

  for (const file of files) {
    const content = await fs.readFile(file, 'utf8');

    for (const rule of rules) {
      rule.regex.lastIndex = 0;
      let match;
      while ((match = rule.regex.exec(content)) !== null) {
        findings.push({
          file: path.relative(process.cwd(), file).replaceAll('\\', '/'),
          line: findLine(content, match.index),
          rule: rule.name,
          sample: match[0].trim(),
          recommendation: rule.recommendation,
        });
      }
    }
  }

  if (findings.length === 0) {
    process.stdout.write('Workflow legacy warning audit passed: no deprecated patterns found.\n');
    return;
  }

  process.stderr.write('Workflow legacy warning audit found deprecated patterns:\n');
  for (const finding of findings) {
    process.stderr.write(
      `- ${finding.file}:${finding.line} [${finding.rule}] ${finding.sample}\n  Fix: ${finding.recommendation}\n`,
    );
  }

  process.exit(1);
}

main().catch((error) => {
  process.stderr.write(`${String(error?.stack || error)}\n`);
  process.exit(1);
});
