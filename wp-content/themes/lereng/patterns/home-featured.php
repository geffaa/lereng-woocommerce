<?php
/**
 * Title: This week's roast
 * Slug: lereng/home-featured
 * Categories: lereng
 */
?>
<!-- wp:group {"align":"wide","className":"section","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide section"><!-- wp:group {"className":"section__head","layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group section__head"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e("This week's roast", 'lereng'); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph --><p><a class="arrow-link" href="/product-category/coffee/"><?php esc_html_e('All coffee', 'lereng'); ?></a></p><!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:woocommerce/product-collection {"queryId":11,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"menu_order","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"collection":"woocommerce/product-collection/featured","hideControls":["inherit","hand-picked","filterable"],"queryContextIncludes":["collection"]} -->
<div class="wp-block-woocommerce-product-collection"><!-- lereng:card -->
<!-- wp:woocommerce/product-template {"className":"product-grid"} -->
<!-- wp:group {"className":"card","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group card"><!-- wp:woocommerce/product-image {"imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"aspectRatio":"4/5","scale":"cover","className":"card__img"} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:lereng/card-label /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"card__title","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:lereng/card-notes /-->
<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"className":"card__price"} /--></div>
<!-- /wp:group -->
<!-- /wp:woocommerce/product-template -->
<!-- /lereng:card --></div>
<!-- /wp:woocommerce/product-collection --></div>
<!-- /wp:group -->
