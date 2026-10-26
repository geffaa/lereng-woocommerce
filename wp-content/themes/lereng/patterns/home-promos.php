<?php
/**
 * Title: Promo tiles
 * Slug: lereng/home-promos
 * Categories: lereng
 */
$tiles = [
    ['discovery-box.jpg', __('Discovery Box', 'lereng'), __('Pick three single origins in 100 g bags and save 15%.', 'lereng'), '/product/discovery-box/'],
    ['brew-kit.jpg', __('Brew Starter Kit', 'lereng'), __('Dripper, filters, server and 200 g of coffee. Everything for your first pour over.', 'lereng'), '/product/brew-starter-kit/'],
    ['wholesale.jpg', __('For cafés and offices', 'lereng'), __('Wholesale prices from 5 kg a month, a dedicated roast day and barista training.', 'lereng'), '/wholesale/'],
];
?>
<!-- wp:group {"align":"wide","className":"section promos","layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
<div class="wp-block-group alignwide section promos">
<?php foreach ($tiles as [$img, $title, $text, $url]) : ?>
<!-- wp:group {"className":"promo","layout":{"type":"constrained"}} -->
<div class="wp-block-group promo"><!-- wp:image {"sizeSlug":"large","className":"promo__img"} -->
<figure class="wp-block-image size-large promo__img"><img src="<?php echo esc_url(get_theme_file_uri("assets/img/$img")); ?>" alt="" loading="lazy" width="1200" height="900"/></figure>
<!-- /wp:image -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h3><!-- /wp:heading -->
<!-- wp:paragraph --><p><?php echo esc_html($text); ?></p><!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php endforeach; ?>
</div>
<!-- /wp:group -->
