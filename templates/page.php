<?php
/**
 * Wrapper for routed single pages (theme, plugin, premium, author).
 * Override by copying to yourtheme/bwing/page.php.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	// Block themes have no header.php/footer.php: render their template parts instead.
	$bwing_header = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
	$bwing_footer = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php echo $bwing_header; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<main id="primary" class="bw-main bw-container">
		<?php echo BWing_Router::render(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</main>
	<?php echo $bwing_footer; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
	<?php
	return;
}

get_header();
?>
<main id="primary" class="bw-main bw-container">
	<?php echo BWing_Router::render(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</main>
<?php
get_footer();
