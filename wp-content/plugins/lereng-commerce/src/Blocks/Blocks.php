<?php

namespace Lereng\Blocks;

use Lereng\Catalog\CoffeeData;
use Lereng\I18n;
use WC_Product;

/**
 * Server-rendered blocks the theme templates place: the origin label and tasting notes on product
 * cards, the coffee facts sheet on product pages, the origins list on the home page and the
 * delivery promise under the add-to-cart button. No editor build step: they are dynamic blocks.
 */
final class Blocks
{
    /** Label colour per island, matching the colour band on each bag. */
    public const ISLAND_COLORS = [
        'Sumatra' => '#2F4B3C', 'Java' => '#2E3D6B', 'Bali' => '#C7902B', 'Nusa Tenggara' => '#C8603F',
        'Sulawesi' => '#6B3554', 'Papua' => '#1E6B67', 'Blend' => '#2B2A27',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerBlocks']);
    }

    public function registerBlocks(): void
    {
        $common = ['api_version' => 3, 'uses_context' => ['postId', 'postType'], 'supports' => ['align' => ['wide', 'full']]];
        register_block_type('lereng/card-label', $common + ['attributes' => ['variant' => ['type' => 'string', 'default' => 'small']], 'render_callback' => [$this, 'cardLabel']]);
        register_block_type('lereng/card-notes', $common + ['attributes' => ['variant' => ['type' => 'string', 'default' => 'line']], 'render_callback' => [$this, 'cardNotes']]);
        register_block_type('lereng/coffee-facts', $common + ['attributes' => ['align' => ['type' => 'string']], 'render_callback' => [$this, 'coffeeFacts']]);
        register_block_type('lereng/origin-list', $common + ['render_callback' => [$this, 'originList']]);
        register_block_type('lereng/delivery-promise', $common + ['render_callback' => [$this, 'deliveryPromise']]);
    }

    private function product($block): ?WC_Product
    {
        $id = $block->context['postId'] ?? get_the_ID();
        $product = $id ? wc_get_product($id) : null;
        return $product instanceof WC_Product ? $product : null;
    }

    private static function attr(WC_Product $p, string $slug): string
    {
        return (string) $p->get_attribute("pa_$slug");
    }

    public function cardLabel(array $attrs, string $content, $block): string
    {
        $p = $this->product($block);
        if (!$p) {
            return '';
        }
        $island = self::attr($p, 'island');
        $parts = array_filter([$island === 'Blend' ? __('Blend', 'lereng') : I18n::text($island), self::attr($p, 'process') === 'Blend' ? '' : I18n::text(self::attr($p, 'process')), self::attr($p, 'roast') ? sprintf(__('%s roast', 'lereng'), I18n::text(self::attr($p, 'roast'))) : '']);
        if (!$parts) {
            $cats = wc_get_product_terms($p->get_id(), 'product_cat', ['orderby' => 'parent', 'order' => 'DESC']);
            $parts = $cats ? [$cats[0]->name] : [];
        }
        $kind = get_post_meta($p->get_id(), '_lereng_kind', true);
        $badge = $kind === 'microlot' ? '<span class="label__badge">' . esc_html__('Micro-lot', 'lereng') . '</span>' : '';
        if (get_post_meta($p->get_id(), '_lereng_preorder', true)) {
            $badge = '<span class="label__badge label__badge--pre">' . esc_html__('Pre-order', 'lereng') . '</span>';
        }
        $color = self::ISLAND_COLORS[$island] ?? '#6B675F';
        return sprintf('<p class="coffee-label coffee-label--%s"><i style="background:%s"></i>%s%s</p>', esc_attr($attrs['variant'] ?? 'small'), esc_attr($color), esc_html(implode(' · ', $parts)), $badge);
    }

    public function cardNotes(array $attrs, string $content, $block): string
    {
        $p = $this->product($block);
        $notes = $p ? get_post_meta($p->get_id(), '_lereng_notes', true) : '';
        if (!$notes) {
            return '';
        }
        $list = array_map([I18n::class, 'text'], array_map('trim', explode(',', $notes)));
        if (($attrs['variant'] ?? 'line') === 'chips') {
            return '<ul class="notes-chips" aria-label="' . esc_attr__('Tasting notes', 'lereng') . '">' . implode('', array_map(static fn($n) => '<li>' . esc_html($n) . '</li>', $list)) . '</ul>';
        }
        return '<p class="card-notes">' . esc_html(implode(', ', $list)) . '</p>';
    }

    public function coffeeFacts(array $attrs, string $content, $block): string
    {
        $p = $this->product($block);
        if (!$p) {
            return '';
        }
        $d = CoffeeData::get($p->get_parent_id() ?: $p->get_id());
        $rows = array_filter([
            __('Producer', 'lereng') => isset($d['producer']) ? I18n::text($d['producer']) : null,
            __('Region', 'lereng') => isset($d['region']) ? I18n::text($d['region'] . (isset($d['province']) ? ', ' . $d['province'] : '')) : null,
            __('Altitude', 'lereng') => $d['altitude'] ?? null,
            __('Varietals', 'lereng') => $d['varietals'] ?? null,
            __('Process', 'lereng') => I18n::text(self::attr($p, 'process')) ?: null,
            __('Harvest', 'lereng') => $d['harvest'] ?? null,
            __('Components', 'lereng') => $d['components'] ?? null,
            __('Cupping score', 'lereng') => isset($d['score']) ? number_format_i18n((float) $d['score'], 2) . ' SCA' : null,
        ]);
        if (!$rows && empty($d['recipe'])) {
            return '';
        }
        $html = '<section class="coffee-facts align' . esc_attr($attrs['align'] ?? 'wide') . '"><div class="coffee-facts__sheet"><h2>' . esc_html__('The coffee', 'lereng') . '</h2><dl>';
        foreach ($rows as $k => $v) {
            $html .= '<div><dt>' . esc_html($k) . '</dt><dd>' . esc_html($v) . '</dd></div>';
        }
        $html .= '</dl></div>';
        if (!empty($d['recipe'])) {
            $html .= '<div class="coffee-facts__recipe"><h2>' . esc_html__('How we brew it', 'lereng') . '</h2><p>' . esc_html(I18n::text($d['recipe'])) . '</p>';
            if (!empty($d['story'])) {
                $html .= '<h3>' . esc_html__('From the farm', 'lereng') . '</h3><p>' . esc_html(I18n::field($p->get_parent_id() ?: $p->get_id(), 'story', $d['story'])) . '</p>';
            }
            $html .= '</div>';
        }
        return $html . '</section>';
    }

    public function originList(): string
    {
        $tax = wc_attribute_taxonomy_name('island');
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
        if (is_wp_error($terms)) {
            return '';
        }
        $order = array_keys(self::ISLAND_COLORS);
        usort($terms, static fn($a, $b) => array_search($a->name, $order, true) <=> array_search($b->name, $order, true));
        $html = '<ul class="origin-list">';
        foreach ($terms as $t) {
            if ($t->name === 'Blend') {
                continue;
            }
            $url = add_query_arg('filter_island', $t->slug, get_permalink(wc_get_page_id('shop')));
            $html .= sprintf('<li><a href="%s"><i style="background:%s"></i><span>%s</span><small>%s</small></a></li>', esc_url($url), esc_attr(self::ISLAND_COLORS[$t->name] ?? '#999'), esc_html(I18n::text($t->name)), esc_html(sprintf(_n('%d coffee', '%d coffees', $t->count, 'lereng'), $t->count)));
        }
        return $html . '</ul>';
    }

    public function deliveryPromise(array $attrs, string $content, $block): string
    {
        $p = $this->product($block);
        if (!$p) {
            return '';
        }
        $pre = get_post_meta($p->get_id(), '_lereng_preorder', true);
        $lines = $pre
            ? [esc_html($pre['note'] ?? '')]
            : [
                esc_html(sprintf(__('Order now and we roast it on %s, then ship the same day', 'lereng'), wp_date('l j F', \Lereng\Catalog\RoastSchedule::nextRoast(new \DateTimeImmutable())->getTimestamp() + 43200))),
                esc_html__('Free delivery in Bali over Rp 300.000 · JNE, SiCepat and GoSend', 'lereng'),
            ];
        return '<ul class="promise">' . implode('', array_map(static fn($l) => "<li>$l</li>", $lines)) . '</ul>';
    }
}
