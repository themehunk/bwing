<?php
/**
 * Wrapper for listing pages: directory (/listing/), category & tag archives,
 * single listing and listing author pages.
 * Override by copying to yourtheme/bwing/listing.php.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

$bwing_view = BWing_Listing::view();

/** Reviews (comments) under single listings. */
$bwing_comments = function () use ( $bwing_view ) {
	if ( 'single' !== $bwing_view || ! ( comments_open() || get_comments_number() ) ) {
		return;
	}
	echo '<section class="bw-panel bw-listing__comments" id="reviews">';
	comments_template();
	echo '</section>';
};

if ( 'single' === $bwing_view ) {
	the_post();
}

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
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
		<?php
		echo BWing_Listing::render(); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'single' === $bwing_view && ( comments_open() || get_comments_number() ) ) {
			echo '<section class="bw-panel bw-listing__comments" id="reviews">' . do_blocks( '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comments-title /--><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-date /--><!-- wp:comment-content /--><!-- wp:comment-reply-link /--><!-- /wp:comment-template --><!-- wp:comments-pagination /--><!-- wp:post-comments-form /--></div><!-- /wp:comments -->' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
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
	<?php
	echo BWing_Listing::render(); // phpcs:ignore WordPress.Security.EscapeOutput
	$bwing_comments();
	?>
</main>
<?php
get_footer();
