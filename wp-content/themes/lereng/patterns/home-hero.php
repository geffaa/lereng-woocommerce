<?php
/**
 * Title: Home hero
 * Slug: lereng/home-hero
 * Categories: lereng
 */
$img = get_theme_file_uri('assets/img/hero.jpg');
?>
<!-- wp:group {"align":"full","className":"hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hero"><!-- wp:columns {"align":"wide","verticalAlignment":"center","className":"hero__cols"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center hero__cols"><!-- wp:column {"verticalAlignment":"center","width":"52%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%"><!-- wp:paragraph {"className":"kicker"} -->
<p class="kicker"><?php esc_html_e('Roasted in Bali · 32 origins across 7 islands', 'lereng'); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1,"fontSize":"display","className":"hero__title"} -->
<h1 class="wp-block-heading hero__title has-display-font-size"><?php esc_html_e('Coffee from the slopes of Indonesia.', 'lereng'); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"hero__lead","fontSize":"large"} -->
<p class="hero__lead has-large-font-size"><?php esc_html_e('Single origins from Gayo to the Baliem Valley, bought directly from the farmers who grow them and roasted twice a week.', 'lereng'); ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/product-category/coffee/"><?php esc_html_e('Shop coffee', 'lereng'); ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/subscriptions/"><?php esc_html_e('Start a subscription', 'lereng'); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->
<!-- wp:column {"width":"48%"} -->
<div class="wp-block-column" style="flex-basis:48%"><!-- wp:image {"sizeSlug":"full","className":"hero__img"} -->
<figure class="wp-block-image size-full hero__img"><img src="<?php echo esc_url($img); ?>" alt="<?php esc_attr_e('Coffee cherries on the branch', 'lereng'); ?>" width="1200" height="1500"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
