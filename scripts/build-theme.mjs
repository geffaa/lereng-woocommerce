/**
 * Inlines shared block markup into the theme's templates and patterns.
 * Block templates can't include a pattern inside a Product Collection (the product template
 * must be a direct child), so the card lives once in theme/src/product-card.html and is copied
 * between <!-- lereng:card --> markers. Run after editing the card: node scripts/build-theme.mjs
 */
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const theme = resolve(import.meta.dirname, '../wp-content/themes/lereng');
const card = readFileSync(join(theme, 'src/product-card.html'), 'utf8').trim();
const block = `<!-- lereng:card -->\n${card}\n<!-- /lereng:card -->`;
let n = 0;
for (const dir of ['templates', 'patterns']) {
  for (const f of readdirSync(join(theme, dir))) {
    const path = join(theme, dir, f);
    const src = readFileSync(path, 'utf8');
    const out = src
      .replace(/<!-- wp:pattern \{"slug":"lereng\/product-card"\} \/-->/g, block)
      .replace(/<!-- lereng:card -->[\s\S]*?<!-- \/lereng:card -->/g, block);
    if (out !== src) {
      writeFileSync(path, out);
      n++;
    }
  }
}
console.log(`card inlined in ${n} files`);
