<?php
/**
 * Seeder entry point, run inside WordPress by `node scripts/wp.mjs seed [step...]`.
 * Steps are idempotent: running one again updates what it made instead of duplicating it.
 */

use Lereng\Seed\Seeder;

$args = preg_split('/\s+/', trim(implode(' ', array_slice($_SERVER['argv'], 1))));
$force = in_array('force', $args, true); // `seed history force` regenerates the history
$steps = array_values(array_filter($args, static fn($a) => $a !== '' && $a !== 'force')) ?: Seeder::STEPS;
$catalog = json_decode(file_get_contents(WP_CONTENT_DIR . '/lereng-data/catalog.json'), true);
$seeder = new Seeder($catalog, 'lereng_out', $force);
foreach ($steps as $step) {
    $t = microtime(true);
    $seeder->run($step);
    lereng_out(sprintf('✓ %s (%.1fs)', $step, microtime(true) - $t));
}
