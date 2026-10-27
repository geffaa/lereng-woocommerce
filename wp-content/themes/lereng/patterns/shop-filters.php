<?php
/**
 * Title: Shop filters
 * Slug: lereng/shop-filters
 * Inserter: no
 * Description: Product filters. Attribute IDs are looked up at render time, so the pattern works on any install.
 */
$attr = static fn(string $slug): int => (int) wc_attribute_taxonomy_id_by_name($slug);
$label = static fn(string $s): string => esc_html($s);
$filters = [['roast', __('Roast', 'lereng')], ['process', __('Process', 'lereng')], ['island', __('Origin', 'lereng')], ['taste', __('Taste', 'lereng')]];
?>
<!-- wp:woocommerce/product-filters {"overlayMode":"mobile","className":"shop-filters"} -->
<div class="wp-block-woocommerce-product-filters shop-filters">
<!-- wp:woocommerce/product-filter-active -->
<div class="wp-block-woocommerce-product-filter-active"><!-- wp:woocommerce/product-filter-removable-chips -->
<div class="wp-block-woocommerce-product-filter-removable-chips wc-block-product-filter-removable-chips"></div>
<!-- /wp:woocommerce/product-filter-removable-chips -->
<!-- wp:woocommerce/product-filter-clear-button -->
<div class="wp-block-woocommerce-product-filter-clear-button"><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-link wc-block-product-filter-clear-button"} --><div class="wp-block-button is-style-link wc-block-product-filter-clear-button"><a class="wp-block-button__link wp-element-button"><?php esc_html_e('Clear filters', 'lereng'); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons --></div>
<!-- /wp:woocommerce/product-filter-clear-button --></div>
<!-- /wp:woocommerce/product-filter-active -->
<?php foreach ($filters as [$slug, $title]) : ?>
<!-- wp:woocommerce/product-filter-attribute {"attributeId":<?php echo $attr($slug); ?>,"showCounts":true,"queryType":"or"} -->
<div class="wp-block-woocommerce-product-filter-attribute"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo $label($title); ?></h3><!-- /wp:heading -->
<!-- wp:woocommerce/product-filter-checkbox-list {"lock":{"remove":true}} --><div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div><!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-attribute -->
<?php endforeach; ?>
<!-- wp:woocommerce/product-filter-price -->
<div class="wp-block-woocommerce-product-filter-price"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php esc_html_e('Price', 'lereng'); ?></h3><!-- /wp:heading -->
<!-- wp:woocommerce/product-filter-price-slider {"lock":{"remove":true}} --><div class="wp-block-woocommerce-product-filter-price-slider wc-block-product-filter-price-slider"></div><!-- /wp:woocommerce/product-filter-price-slider --></div>
<!-- /wp:woocommerce/product-filter-price -->
<!-- wp:woocommerce/product-filter-status -->
<div class="wp-block-woocommerce-product-filter-status"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php esc_html_e('Availability', 'lereng'); ?></h3><!-- /wp:heading -->
<!-- wp:woocommerce/product-filter-checkbox-list {"lock":{"remove":true}} --><div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div><!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-status -->
</div>
<!-- /wp:woocommerce/product-filters -->
