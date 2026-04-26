const TODO_PATTERN = /(?:^|[^A-Za-z0-9_])(?:TODO|FIXME)(?=$|[^A-Za-z0-9_])/;
const IGNORED_PATH_PREFIXES = [
  'vendor/',
  'node_modules/',
  'storage/',
  '.git/',
];
const IGNORED_FILES = new Set([
  'scripts/ci/check-todo-governance.mjs',
]);

async function readStdin() {
  const chunks = [];
  for await (const chunk of process.stdin) {
    chunks.push(typeof chunk === 'string' ? chunk : chunk.toString('utf8'));
  }

  return chunks.join('');
}

function shouldIgnoreFile(filePath) {
  return IGNORED_FILES.has(filePath) || IGNORED_PATH_PREFIXES.some((prefix) => filePath.startsWith(prefix));
}

function parseFindings(diffText) {
  const findings = [];
  let currentFile = '';
  let nextLine = 0;

  const lines = diffText.split('\n');
  for (const line of lines) {
    if (line.startsWith('+++ b/')) {
      currentFile = line.slice('+++ b/'.length).trim();
      continue;
    }

    const hunkMatch = line.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
    if (hunkMatch) {
      nextLine = Number.parseInt(hunkMatch[1], 10) - 1;
      continue;
    }

    if (line.startsWith('+') && !line.startsWith('+++')) {
      nextLine += 1;
      if (!currentFile || shouldIgnoreFile(currentFile)) {
        continue;
      }

      if (TODO_PATTERN.test(line.slice(1))) {
        findings.push({
          file: currentFile,
          line: nextLine,
          text: line.slice(1).trim(),
        });
      }
    }
  }

  return findings;
}

const diffText = await readStdin();
if (!diffText.trim()) {
  process.stdout.write('TODO governance passed: no diff content provided on stdin.\n');
  process.exit(0);
}

const findings = parseFindings(diffText);
if (findings.length === 0) {
  process.stdout.write('TODO governance passed: no new TODO/FIXME markers detected in diff.\n');
  process.exit(0);
}

process.stderr.write('TODO governance failed: found new TODO/FIXME markers in diff.\n');
for (const finding of findings) {
  process.stderr.write(`- ${finding.file}:${finding.line} ${finding.text}\n`);
}
process.stderr.write('Resolve or justify markers before closure.\n');
process.exit(1);
