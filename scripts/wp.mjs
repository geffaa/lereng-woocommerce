/**
 * Local WordPress for development, on WordPress Playground (PHP compiled to WebAssembly):
 * no PHP, MySQL or Docker setup, and the same runtime the live demo uses.
 *
 *   node scripts/wp.mjs setup          install WordPress + pinned WooCommerce into .wp/site (once)
 *   node scripts/wp.mjs serve [--login] http://127.0.0.1:9400 with the theme, plugin and data mounted
 *                                      (--login signs every visitor in as admin; leave it off to shop as a guest)
 *   node scripts/wp.mjs php <file>     run a PHP file inside the site (output is printed)
 *   node scripts/wp.mjs seed [step]    run the catalog / customer / order seeders
 *
 * The site lives in .wp/site (SQLite database included), so data survives restarts.
 */
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync, copyFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..');
const SITE = `${ROOT}/.wp/site`;
const CLI = '@wp-playground/cli@3.1.61';
export const WC_VERSION = '11.2.1';
const PHP = '8.3';

const MOUNTS = [
  `${ROOT}/wp-content/plugins/lereng-commerce:/wordpress/wp-content/plugins/lereng-commerce`,
  `${ROOT}/wp-content/themes/lereng:/wordpress/wp-content/themes/lereng`,
  `${ROOT}/data:/wordpress/wp-content/lereng-data`,
];

function playground(command, extra = []) {
  const args = ['-y', CLI, command, `--php=${PHP}`, `--mount-before-install=${SITE}:/wordpress`, '--wordpress-install-mode=install-from-existing-files-if-needed', ...MOUNTS.flatMap((m) => ['--mount', m]), ...extra];
  return new Promise((done, fail) => {
    const p = spawn('npx', args, { stdio: ['ignore', 'pipe', 'pipe'] });
    p.stdout.on('data', (d) => process.stdout.write(d));
    p.stderr.on('data', (d) => /warn/i.test(d) || process.stderr.write(d));
    p.on('exit', (code) => (code ? fail(new Error(`playground exited ${code}`)) : done()));
  });
}

/** Runs PHP inside the site. The script writes to $GLOBALS['lereng_out'] via lereng_out(). */
async function runPhp(file, args = []) {
  const out = `${SITE}/wp-content/lereng-out.txt`;
  rmSync(out, { force: true });
  const bp = `${ROOT}/.wp/bp-run.json`;
  const code = `<?php
    $_SERVER['argv'] = ${JSON.stringify(['run', ...args])};
    define('LERENG_CLI', true);
    function lereng_out($s) { file_put_contents('/wordpress/wp-content/lereng-out.txt', $s . "\\n", FILE_APPEND); }
    require '/wordpress/wp-load.php';
    try { require ${JSON.stringify(file)}; } catch (Throwable $e) { lereng_out('ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()); }`;
  writeFileSync(bp, JSON.stringify({ steps: [{ step: 'runPHP', code }] }));
  await playground('run-blueprint', [`--blueprint=${bp}`]);
  if (existsSync(out)) process.stdout.write(readFileSync(out, 'utf8'));
}

const [cmd, ...rest] = process.argv.slice(2);
if (cmd === 'setup') {
  mkdirSync(SITE, { recursive: true });
  const zip = `${ROOT}/.wp/woocommerce.${WC_VERSION}.zip`;
  if (!existsSync(zip)) {
    const res = await fetch(`https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip`);
    writeFileSync(zip, Buffer.from(await res.arrayBuffer()));
  }
  const bp = `${ROOT}/.wp/bp-setup.json`;
  writeFileSync(bp, JSON.stringify({
    preferredVersions: { php: PHP, wp: 'latest' },
    steps: [
      { step: 'installPlugin', pluginData: { resource: 'bundled', path: `/woocommerce.${WC_VERSION}.zip` }, options: { activate: true } },
      { step: 'activatePlugin', pluginPath: 'lereng-commerce/lereng-commerce.php' },
      { step: 'activateTheme', themeFolderName: 'lereng' },
    ],
  }));
  await playground('run-blueprint', [`--blueprint=${bp}`, '--blueprint-may-read-adjacent-files']);
  console.log('Site ready in .wp/site');
} else if (cmd === 'serve') {
  await playground('server', ['--port=9400', ...rest]);
} else if (cmd === 'php') {
  await runPhp(`/wordpress/wp-content/${rest[0].replace(/^wp-content\//, '')}`, rest.slice(1));
} else if (cmd === 'seed') {
  await runPhp('/wordpress/wp-content/plugins/lereng-commerce/seed/run.php', rest);
} else {
  console.log('usage: node scripts/wp.mjs setup|serve|php <file>|seed [step]');
}
