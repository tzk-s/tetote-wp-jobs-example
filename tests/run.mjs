/**
 * 結合テストを WordPress Playground（CLI）で実行する
 *
 * 毎回まっさらな WordPress を起動し、プラグインを有効化して tests/integration.php を実行する。
 * run-blueprint は PHP の出力を表示しないため、結果を書き出すフォルダーをマウントして読み取る。
 * 追加の引数は wp-playground-cli にそのまま渡す（例: npm test -- --wp=6.8）。
 *
 * --wp と --php は、Blueprint の preferredVersions に書き込んで渡す。
 * Playground CLI はファイルの Blueprint を受け取ると、コマンドラインの --wp / --php を使わず
 * Blueprint の指定（無ければ最新版）で起動するため。
 */
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const output = mkdtempSync(join(tmpdir(), 'tetote-jobs-test-'));

// PHP は Docker・Playground のデモと同じ 8.5 を既定にする（--php=8.3 などで上書きできる）
const extra = process.argv.slice(2);
const option = (name, fallback) => {
  const found = extra.find((arg) => arg.startsWith(`--${name}=`));
  return found ? found.slice(name.length + 3) : fallback;
};
const blueprint = JSON.parse(readFileSync(join(root, 'tests/blueprint.test.json'), 'utf8'));
blueprint.preferredVersions = { wp: option('wp', 'latest'), php: option('php', '8.5') };
const blueprintFile = join(output, 'blueprint.json');
writeFileSync(blueprintFile, JSON.stringify(blueprint));

const args = [
  'wp-playground-cli',
  'run-blueprint',
  `--mount=${join(root, 'tetote-jobs')}:/wordpress/wp-content/plugins/tetote-jobs`,
  `--mount=${join(root, 'tests')}:/tetote-tests`,
  `--mount=${output}:/tetote-test-output`,
  `--blueprint=${blueprintFile}`,
  ...extra.filter((arg) => !arg.startsWith('--wp=') && !arg.startsWith('--php=')),
];

const run = spawnSync('npx', args, { cwd: root, encoding: 'utf8' });
const resultFile = join(output, 'result.txt');
const result = existsSync(resultFile) ? readFileSync(resultFile, 'utf8') : '';
rmSync(output, { recursive: true, force: true });

if (result) process.stdout.write(result);
if (run.status !== 0 || !result) {
  process.stderr.write(run.stdout + run.stderr);
  process.stderr.write('\nテストが失敗したか、WordPress を起動できませんでした。\n');
  process.exit(1);
}
