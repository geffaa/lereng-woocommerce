<?php
/**
 * Lereng theme: block theme. Design tokens live in theme.json; this file only loads assets
 * and registers pattern categories.
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', static function (): void {
    $v = wp_get_theme()->get('Version');
    wp_enqueue_style('lereng', get_theme_file_uri('assets/css/theme.css'), [], $v);
    wp_enqueue_script('lereng', get_theme_file_uri('assets/js/theme.js'), [], $v, ['strategy' => 'defer', 'in_footer' => true]);
});

add_action('init', static function (): void {
    register_block_pattern_category('lereng', ['label' => __('Lereng', 'lereng')]);
});
