/**
 * Builds the live demo: a WordPress Playground blueprint plus a zip of the store's content.
 *
 *   node scripts/demo.mjs [--base=https://geffaa.github.io/lereng-woocommerce/demo]
 *
 * Writes docs/demo/site.zip (theme, plugin, uploads, language packs and the SQLite database with
 * the seeded year of orders) and docs/demo/blueprint.json. playground.wordpress.net loads the
 * blueprint, imports the zip, installs the pinned WooCommerce from wordpress.org and turns on
 * demo mode (LERENG_DEMO): the whole store then runs in the visitor's browser.
 */
import { spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..');
const SITE = `${ROOT}/.wp/site`;
const OUT = `${ROOT}/docs/demo`;
const base = (process.argv.find((a) => a.startsWith('--base='))?.slice(7) || 'https://geffaa.github.io/lereng-woocommerce/demo').replace(/\/$/, '');
const STAGE = `${ROOT}/.wp/demo-stage`;
// Same WooCommerce version as development (read from wp.mjs, which runs a CLI when imported).
const WC_VERSION = /WC_VERSION = '([^']+)'/.exec(readFileSync(`${ROOT}/scripts/wp.mjs`, 'utf8'))[1];

rmSync(STAGE, { recursive: true, force: true });
mkdirSync(`${STAGE}/wp-content/plugins`, { recursive: true });
mkdirSync(`${STAGE}/wp-content/themes`, { recursive: true });
const skip = (src) => !/\/(vendor|tests|node_modules|\.phpunit\.cache|src\/product-card\.html)(\/|$)|phpunit\.xml|composer\.(json|lock)$/.test(src);
cpSync(`${ROOT}/wp-content/plugins/lereng-commerce`, `${STAGE}/wp-content/plugins/lereng-commerce`, { recursive: true, filter: skip });
cpSync(`${ROOT}/wp-content/themes/lereng`, `${STAGE}/wp-content/themes/lereng`, { recursive: true, filter: (s) => !/\/src(\/|$)/.test(s) });
for (const dir of ['uploads', 'languages', 'database', 'fonts']) {
  if (existsSync(`${SITE}/wp-content/${dir}`)) cpSync(`${SITE}/wp-content/${dir}`, `${STAGE}/wp-content/${dir}`, { recursive: true });
}
// The plugin reads its seed data only when seeding; the demo needs none of it.
mkdirSync(OUT, { recursive: true });
rmSync(`${OUT}/site.zip`, { force: true });
const zip = spawnSync('zip', ['-qr9', `${OUT}/site.zip`, 'wp-content', '-x', '*.DS_Store', '*/debug.log'], { cwd: STAGE, stdio: 'inherit' });
if (zip.status) process.exit(zip.status);

const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  meta: { title: 'Lereng Coffee Roasters', description: 'A WooCommerce store for an Indonesian specialty coffee roaster, running in your browser.', author: 'geffaa' },
  landingPage: '/',
  preferredVersions: { php: '8.3', wp: '7.0' },
  features: { networking: true },
  login: false,
  steps: [
    { step: 'importWordPressFiles', wordPressFilesZip: { resource: 'url', url: `${base}/site.zip` } },
    { step: 'installPlugin', pluginData: { resource: 'url', url: `https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip` }, options: { activate: true } },
    { step: 'defineWpConfigConsts', consts: { LERENG_DEMO: true, WP_DEBUG: false } },
  ],
};
writeFileSync(`${OUT}/blueprint.json`, JSON.stringify(blueprint, null, 2) + '\n');
console.log(`site.zip ${(statSync(`${OUT}/site.zip`).size / 1e6).toFixed(1)} MB, blueprint → ${base}/blueprint.json`);
console.log(`open: https://playground.wordpress.net/?blueprint-url=${encodeURIComponent(`${base}/blueprint.json`)}`);
