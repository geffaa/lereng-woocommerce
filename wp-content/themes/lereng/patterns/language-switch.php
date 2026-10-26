<?php
/**
 * Title: Language switch
 * Slug: lereng/language-switch
 * Inserter: no
 */
$current = function_exists('lereng_language') ? lereng_language() : 'en';
?>
<!-- wp:html -->
<nav class="lang-switch" aria-label="<?php esc_attr_e('Language', 'lereng'); ?>">
	<a href="<?php echo esc_url(add_query_arg('lang', 'en')); ?>" lang="en" <?php echo $current === 'en' ? 'aria-current="true"' : ''; ?>>EN</a>
	<a href="<?php echo esc_url(add_query_arg('lang', 'id')); ?>" lang="id" <?php echo $current === 'id' ? 'aria-current="true"' : ''; ?>>ID</a>
</nav>
<!-- /wp:html -->
