<?php
/**
 * Title: Footer content
 * Slug: lereng/footer-content
 * Inserter: no
 */
?>
<!-- wp:group {"align":"wide","className":"footer-top","layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
<div class="wp-block-group alignwide footer-top">
<!-- wp:group {"className":"footer-brand","layout":{"type":"default"}} -->
<div class="wp-block-group footer-brand"><!-- wp:heading {"level":2,"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size"><?php esc_html_e('Coffee from the slopes of Indonesia.', 'lereng'); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e('Roastery and tasting bar: Jl. Raya Kerobokan No. 88, Badung, Bali. Open Tuesday to Sunday, 8:00–17:00.', 'lereng'); ?></p>
<!-- /wp:paragraph -->
<!-- wp:html -->
<form class="newsletter" action="#" method="post" data-newsletter>
	<label for="nl-email" class="screen-reader-text"><?php esc_html_e('Email', 'lereng'); ?></label>
	<input id="nl-email" type="email" name="email" required placeholder="<?php esc_attr_e('Email for new roasts', 'lereng'); ?>">
	<button type="submit"><?php esc_html_e('Subscribe', 'lereng'); ?></button>
	<p class="newsletter__msg" role="status" hidden></p>
</form>
<!-- /wp:html --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"footer-col","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group footer-col"><!-- wp:paragraph {"className":"footer-label"} --><p class="footer-label"><?php esc_html_e('Shop', 'lereng'); ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/product-category/coffee/single-origin/"><?php esc_html_e('Single origins', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/product-category/coffee/blends/"><?php esc_html_e('Blends', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/product-category/coffee/micro-lots/"><?php esc_html_e('Micro-lots', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/product-category/equipment/"><?php esc_html_e('Equipment', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/product/lereng-gift-card/"><?php esc_html_e('Gift cards', 'lereng'); ?></a></p><!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"footer-col","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group footer-col"><!-- wp:paragraph {"className":"footer-label"} --><p class="footer-label"><?php esc_html_e('Help', 'lereng'); ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/track-order/"><?php esc_html_e('Track an order', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/shipping/"><?php esc_html_e('Shipping and returns', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/subscriptions/"><?php esc_html_e('How subscriptions work', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/my-account/"><?php esc_html_e('My account', 'lereng'); ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a href="/wholesale/"><?php esc_html_e('Wholesale for cafés', 'lereng'); ?></a></p><!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"footer-bottom","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide footer-bottom"><!-- wp:paragraph {"fontSize":"xs"} -->
<p class="has-xs-font-size">© <?php echo esc_html(wp_date('Y')); ?> Lereng Coffee Roasters · <?php esc_html_e('Prices include 11% PPN', 'lereng'); ?> · <?php esc_html_e('A portfolio project; Lereng is a fictional brand.', 'lereng'); ?></p>
<!-- /wp:paragraph -->
<!-- wp:html -->
<ul class="pay-list" aria-label="<?php esc_attr_e('Payment and delivery', 'lereng'); ?>"><li>QRIS</li><li>BCA VA</li><li>Mandiri VA</li><li>Visa / Mastercard</li><li>JNE</li><li>SiCepat</li><li>GoSend</li></ul>
<!-- /wp:html --></div>
<!-- /wp:group -->
