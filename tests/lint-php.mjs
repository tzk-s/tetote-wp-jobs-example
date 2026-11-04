/**
 * PHP の構文チェック（php -l）。手元に PHP 8.0 以上が必要
 */
import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const files = [];
const walk = dir => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path);
    else if (name.endsWith('.php')) files.push(path);
  }
};
walk(join(root, 'tetote-jobs'));
walk(join(root, 'tests'));

let failed = 0;
for (const file of files) {
  const run = spawnSync('php', ['-l', file], { encoding: 'utf8' });
  if (run.status !== 0) {
    failed += 1;
    process.stderr.write(run.stdout + run.stderr);
  }
}
console.log(`${files.length - failed}/${files.length} files OK`);
process.exit(failed ? 1 : 0);
