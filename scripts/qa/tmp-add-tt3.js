const fs = require('fs');
const path = 'C:/Projects/conecta-hn - copia/app/Http/Middleware/SecurityHeaders.php';
let content = fs.readFileSync(path, 'utf8');
const oldFragment = '"object-src ' + "'" + 'none",' + '\n        ];';
const replacement = '"object-src ' + "'" + 'none",' + '\n            $isProduction ? "require-trusted-types-for ' + "'" + 'script", "trusted-types dovra-policy ' + "'" + 'allow-duplicates",' + '\n            "trusted-types dovra-policy ' + "'" + 'allow-duplicates",' + '\n        ];';
const idx = content.indexOf(oldFragment);
if (idx === -1) { console.log('Fragment not found'); process.exit(1); }
console.log('Found at:', idx);
const newContent = content.substring(0, idx) + replacement + content.substring(idx + oldFragment.length);
fs.writeFileSync(path, newContent, 'utf8');
console.log('Updated');
