<?php
/**
 * Creates the default pages (with shortcodes), menu and sample posts.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Pages {

	const OPTION = 'bwing_pages';

	private static function sc( $code ) {
		return "<!-- wp:shortcode -->\n" . $code . "\n<!-- /wp:shortcode -->\n\n";
	}

	private static function pair( $a, $b ) {
		return "<!-- wp:group {\"className\":\"bw-pair\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group bw-pair\">" . self::sc( $a ) . self::sc( $b ) . "</div>\n<!-- /wp:group -->\n\n";
	}

	/** slug => [title, content, hide_title]. */
	public static function definitions() {
		$home = self::sc( '[bwing_hero]' )
			. self::sc( '[bwing_trending type="both"]' )
			. self::sc( '[bwing_notable]' )
			. self::pair( '[bwing_top type="theme"]', '[bwing_top type="plugin"]' )
			. self::pair( '[bwing_top_premium type="theme"]', '[bwing_top_premium type="plugin"]' )
			. self::pair( '[bwing_top_authors type="theme"]', '[bwing_top_authors type="plugin"]' )
			. self::pair( '[bwing_text section="about"]', '[bwing_text section="ecosystem"]' )
			. self::sc( '[bwing_beyond]' )
			. self::sc( '[bwing_faq]' )
			. self::sc( '[bwing_cta]' );

		return array(
			'home'                     => array( __( 'Home', 'bwing' ), $home, 1 ),
			'themes'                   => array( __( 'Most Popular WordPress Themes', 'bwing' ), self::sc( '[bwing_subnav group="themes" current="themes"]' ) . self::sc( '[bwing_ranking type="theme"]' ), 1 ),
			'plugins'                  => array( __( 'Most Popular WordPress Plugins', 'bwing' ), self::sc( '[bwing_subnav group="plugins" current="plugins"]' ) . self::sc( '[bwing_ranking type="plugin"]' ), 1 ),
			'premium-themes'           => array( __( 'Top Premium WordPress Themes', 'bwing' ), self::sc( '[bwing_subnav group="themes" current="premium-themes"]' ) . self::sc( '[bwing_premium_ranking type="theme"]' ), 1 ),
			'premium-plugins'          => array( __( 'Top Premium WordPress Plugins', 'bwing' ), self::sc( '[bwing_subnav group="plugins" current="premium-plugins"]' ) . self::sc( '[bwing_premium_ranking type="plugin"]' ), 1 ),
			'wordpress-theme-stats'    => array( __( 'WordPress Theme Statistics', 'bwing' ), self::sc( '[bwing_subnav group="themes" current="wordpress-theme-stats"]' ) . self::sc( '[bwing_stats type="theme"]' ), 1 ),
			'wordpress-plugin-stats'   => array( __( 'WordPress Plugin Ecosystem', 'bwing' ), self::sc( '[bwing_subnav group="plugins" current="wordpress-plugin-stats"]' ) . self::sc( '[bwing_stats type="plugin"]' ), 1 ),
			'wordpress-author-stats'   => array( __( 'WordPress Author Statistics', 'bwing' ), self::sc( '[bwing_subnav group="authors" current="wordpress-author-stats"]' ) . self::sc( '[bwing_stats type="author"]' ), 1 ),
			'fastest-wordpress-themes' => array( __( 'Fastest WordPress Themes', 'bwing' ), self::sc( '[bwing_subnav group="themes" current="fastest-wordpress-themes"]' ) . self::sc( '[bwing_fastest]' ), 1 ),
			'most-detected-themes'     => array( __( 'Most Detected WordPress Themes', 'bwing' ), self::sc( '[bwing_subnav group="themes" current="most-detected-themes"]' ) . self::sc( '[bwing_most_detected type="theme" count="50" show_title="yes"]' ), 1 ),
			'most-detected-plugins'    => array( __( 'Most Detected WordPress Plugins', 'bwing' ), self::sc( '[bwing_subnav group="plugins" current="most-detected-plugins"]' ) . self::sc( '[bwing_most_detected type="plugin" count="50" show_title="yes"]' ), 1 ),
			'authors'                  => array( __( 'WordPress.org Authors', 'bwing' ), self::sc( '[bwing_subnav group="authors" current="authors"]' ) . self::sc( '[bwing_authors]' ), 1 ),
			'theme-detector'           => array( __( 'WordPress Theme Detector', 'bwing' ), self::sc( '[bwing_subnav group="tools" current="theme-detector"]' ) . self::sc( '[bwing_detector]' ), 1 ),
			'hosting-checker'          => array( __( 'Hosting Checker', 'bwing' ), self::sc( '[bwing_subnav group="tools" current="hosting-checker"]' ) . self::sc( '[bwing_hosting_checker]' ), 1 ),
			'about'                    => array( __( 'About', 'bwing' ), "<!-- wp:paragraph {\"className\":\"bw-lead\"} -->\n<p class=\"bw-lead\">" . esc_html__( 'BlogWings tracks the WordPress ecosystem every single day so you can see what is really growing — not just what has the biggest number.', 'bwing' ) . "</p>\n<!-- /wp:paragraph -->\n\n" . self::sc( '[bwing_counters]' ) . self::sc( '[bwing_text section="about"]' ) . self::sc( '[bwing_text section="ecosystem"]' ) . self::sc( '[bwing_beyond]' ) . self::sc( '[bwing_newsletter]' ), 0 ),
			'blog'                     => array( __( 'Blog', 'bwing' ), '', 0 ),
			'privacy-policy'           => array( __( 'Privacy Policy', 'bwing' ), "<!-- wp:paragraph -->\n<p>" . esc_html__( 'We collect only what is needed to run this site: anonymous analytics, the email address you give us for the newsletter, and the domains you scan with our tools. We never sell data. Contact us to have your email removed at any time.', 'bwing' ) . "</p>\n<!-- /wp:paragraph -->", 0 ),
		);
	}

	public static function create( $force = false ) {
		$map = get_option( self::OPTION, array() );
		foreach ( self::definitions() as $slug => $def ) {
			if ( ! $force && ! empty( $map[ $slug ] ) && get_post( $map[ $slug ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $slug );
			if ( $existing && 'trash' !== $existing->post_status ) {
				if ( $force ) {
					wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $def[1], 'post_title' => $def[0] ) );
				}
				$map[ $slug ] = $existing->ID;
			} else {
				$map[ $slug ] = wp_insert_post( array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $def[0],
					'post_content' => $def[1],
				) );
			}
			if ( $map[ $slug ] && ! is_wp_error( $map[ $slug ] ) ) {
				update_post_meta( $map[ $slug ], '_bwing_hide_title', $def[2] );
				if ( 'blog' !== $slug && 'privacy-policy' !== $slug ) {
					update_post_meta( $map[ $slug ], '_wp_page_template', 'templates/full-width.php' );
				}
			}
		}
		update_option( self::OPTION, $map );

		// Reading settings.
		if ( ! empty( $map['home'] ) && ( $force || 'page' !== get_option( 'show_on_front' ) ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $map['home'] );
			update_option( 'page_for_posts', $map['blog'] );
		}
		if ( ! empty( $map['privacy-policy'] ) && ! get_option( 'wp_page_for_privacy_policy' ) ) {
			update_option( 'wp_page_for_privacy_policy', $map['privacy-policy'] );
		}

		self::menus( $map );
		self::posts();
	}

	private static function menus( $map ) {
		$link = function ( $menu_id, $slug, $title, $parent = 0, $classes = '' ) use ( $map ) {
			$args = array(
				'menu-item-title'     => $title,
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
				'menu-item-classes'   => $classes,
			);
			if ( ! empty( $map[ $slug ] ) ) {
				$args['menu-item-object']    = 'page';
				$args['menu-item-object-id'] = $map[ $slug ];
				$args['menu-item-type']      = 'post_type';
			} else {
				$args['menu-item-url']  = bwing_url( $slug );
				$args['menu-item-type'] = 'custom';
			}
			return wp_update_nav_menu_item( $menu_id, 0, $args );
		};

		if ( ! wp_get_nav_menu_object( 'BWing Primary' ) ) {
			$m = wp_create_nav_menu( 'BWing Primary' );
			if ( ! is_wp_error( $m ) ) {
				$t = $link( $m, 'themes', __( 'Themes', 'bwing' ), 0, 'bw-mega bw-mega--theme' );
				$link( $m, 'themes', __( 'Most popular themes', 'bwing' ), $t );
				$link( $m, 'premium-themes', __( 'Premium themes', 'bwing' ), $t );
				$link( $m, 'wordpress-theme-stats', __( 'Theme stats', 'bwing' ), $t );
				$link( $m, 'fastest-wordpress-themes', __( 'Fastest themes', 'bwing' ), $t );
				$link( $m, 'most-detected-themes', __( 'Most detected themes', 'bwing' ), $t );
				$p = $link( $m, 'plugins', __( 'Plugins', 'bwing' ), 0, 'bw-mega bw-mega--plugin' );
				$link( $m, 'plugins', __( 'Most popular plugins', 'bwing' ), $p );
				$link( $m, 'premium-plugins', __( 'Premium plugins', 'bwing' ), $p );
				$link( $m, 'wordpress-plugin-stats', __( 'Plugin ecosystem', 'bwing' ), $p );
				$link( $m, 'most-detected-plugins', __( 'Most detected plugins', 'bwing' ), $p );
				$a = $link( $m, 'authors', __( 'Authors', 'bwing' ), 0, 'bw-mega bw-mega--author' );
				$link( $m, 'authors', __( 'Author leaderboard', 'bwing' ), $a );
				$link( $m, 'wordpress-author-stats', __( 'Author stats', 'bwing' ), $a );
				$tl = $link( $m, 'theme-detector', __( 'Tools', 'bwing' ) );
				$link( $m, 'theme-detector', __( 'Theme & plugin detector', 'bwing' ), $tl );
				$link( $m, 'hosting-checker', __( 'Hosting checker', 'bwing' ), $tl );
				$link( $m, 'blog', __( 'Blog', 'bwing' ) );
				$link( $m, 'about', __( 'About', 'bwing' ) );
				update_option( 'bwing_menu_primary', $m );
			}
		}
		if ( ! wp_get_nav_menu_object( 'BWing Footer' ) ) {
			$f = wp_create_nav_menu( 'BWing Footer' );
			if ( ! is_wp_error( $f ) ) {
				foreach ( array( 'about' => __( 'About', 'bwing' ), 'blog' => __( 'Blog', 'bwing' ), 'theme-detector' => __( 'Theme detector', 'bwing' ), 'hosting-checker' => __( 'Hosting checker', 'bwing' ), 'privacy-policy' => __( 'Privacy policy', 'bwing' ) ) as $slug => $title ) {
					$link( $f, $slug, $title );
				}
				update_option( 'bwing_menu_footer', $f );
			}
		}
		self::assign_menus();
	}

	/** Assign menus to the active theme's locations if empty. */
	public static function assign_menus() {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$reg       = get_registered_nav_menus();
		$changed   = false;
		foreach ( array( 'primary' => 'bwing_menu_primary', 'footer' => 'bwing_menu_footer' ) as $loc => $opt ) {
			if ( isset( $reg[ $loc ] ) && empty( $locations[ $loc ] ) && get_option( $opt ) ) {
				$locations[ $loc ] = (int) get_option( $opt );
				$changed           = true;
			}
		}
		if ( $changed ) {
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	private static function posts() {
		if ( get_option( 'bwing_posts_created' ) ) {
			return;
		}
		$term  = term_exists( 'ecosystem', 'category' );
		$term  = $term ? $term : wp_insert_term( __( 'Ecosystem', 'bwing' ), 'category', array( 'slug' => 'ecosystem' ) );
		$cat   = is_wp_error( $term ) ? 1 : (int) $term['term_id'];
		$posts = array(
			array(
				__( 'Block themes crossed 15% of the directory — here is what changed', 'bwing' ),
				__( 'Block themes keep growing as a share of the WordPress.org theme directory. We looked at a year of daily snapshots to see which ones are actually gaining installs, and which classic themes are holding their ground.', 'bwing' ),
			),
			array(
				__( 'Why install brackets make plugins look like they jump overnight', 'bwing' ),
				__( 'WordPress.org rounds active installs into brackets like 10K+ and 1M+. That means a plugin can look flat for months and then leap a whole bracket in one day. Here is how to read the movement columns properly.', 'bwing' ),
			),
			array(
				__( 'The 10 fastest free WordPress themes we tested this quarter', 'bwing' ),
				__( 'We ran Lighthouse against popular themes on a clean install with identical demo content. Lightweight classic themes still lead on mobile, but a few block themes are now right behind them.', 'bwing' ),
			),
		);
		foreach ( $posts as $i => $p ) {
			wp_insert_post( array(
				'post_title'    => $p[0],
				'post_content'  => "<!-- wp:paragraph -->\n<p>" . esc_html( $p[1] ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>" . esc_html__( 'What the data shows', 'bwing' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html__( 'Every number in this article comes from our daily snapshots. Open any theme or plugin page to see the full ranking, install and download history behind it.', 'bwing' ) . "</p>\n<!-- /wp:paragraph -->\n\n" . self::sc( '[bwing_top type="theme" count="5" title="Top 5 themes today" link_text=""]' ),
				'post_status'   => 'publish',
				'post_category' => array( $cat ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 5 + 1 ) . ' days' ) ),
			) );
		}
		update_option( 'bwing_posts_created', 1 );
	}
}
