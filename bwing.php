<?php
/**
 * Plugin Name:       BWing
 * Plugin URI:        https://example.com/bwing
 * Description:       Daily rankings tracker for WordPress.org themes, plugins and authors. Premium rankings, theme & plugin detector, hosting checker, statistics, notable moves, FAQ and newsletter — every section has a dashboard and a shortcode. Built to pair with the BlogWings theme.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            BlogWings
 * License:           GPL-2.0-or-later
 * Text Domain:       bwing
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

define( 'BWING_VERSION', '1.3.0' );
define( 'BWING_FILE', __FILE__ );
define( 'BWING_DIR', plugin_dir_path( __FILE__ ) );
define( 'BWING_URL', plugin_dir_url( __FILE__ ) );

require_once BWING_DIR . 'includes/class-bwing-db.php';
require_once BWING_DIR . 'includes/class-bwing-settings.php';
require_once BWING_DIR . 'includes/class-bwing-format.php';
require_once BWING_DIR . 'includes/class-bwing-data.php';
require_once BWING_DIR . 'includes/class-bwing-sync.php';
require_once BWING_DIR . 'includes/class-bwing-seed.php';
require_once BWING_DIR . 'includes/class-bwing-detector.php';
require_once BWING_DIR . 'includes/class-bwing-router.php';
require_once BWING_DIR . 'includes/class-bwing-shortcodes.php';
require_once BWING_DIR . 'includes/class-bwing-ajax.php';
require_once BWING_DIR . 'includes/class-bwing-pages.php';

if ( is_admin() ) {
	require_once BWING_DIR . 'admin/class-bwing-admin.php';
}

/**
 * Activation: tables, default content, pages, cron.
 */
function bwing_activate() {
	BWing_DB::install();
	BWing_Settings::install_defaults();
	BWing_Seed::run();
	BWing_Pages::create();
	if ( ! wp_next_scheduled( 'bwing_daily_sync' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'bwing_daily_sync' );
	}
	BWing_Router::add_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bwing_activate' );

function bwing_deactivate() {
	wp_clear_scheduled_hook( 'bwing_daily_sync' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bwing_deactivate' );

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'bwing', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	BWing_DB::maybe_upgrade();
} );

add_action( 'init', array( 'BWing_Router', 'init' ) );
add_action( 'init', array( 'BWing_Shortcodes', 'register' ) );
add_action( 'init', array( 'BWing_Ajax', 'init' ) );
add_action( 'bwing_daily_sync', array( 'BWing_Sync', 'run_all' ) );
add_action( 'bwing_sync_continue', array( 'BWing_Sync', 'continue_job' ) );
add_action( 'init', array( 'BWing_Sync', 'maybe_server_cron' ), 99 );

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'bwing', BWING_URL . 'assets/css/bwing.css', array(), BWING_VERSION );
	wp_register_script( 'bwing', BWING_URL . 'assets/js/bwing.js', array(), BWING_VERSION, true );
	wp_localize_script( 'bwing', 'bwingData', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'bwing_front' ),
		'detector' => bwing_url( 'theme-detector' ),
		'i18n'  => array(
			'scanning'   => __( 'Scanning…', 'bwing' ),
			'error'      => __( 'Something went wrong. Please try again.', 'bwing' ),
			'subscribed' => __( 'Thanks! You are subscribed.', 'bwing' ),
		),
	) );
	if ( ! BWing_Settings::get( 'general', 'load_css_everywhere' ) ) {
		// Assets are enqueued lazily by shortcodes; the BlogWings theme enqueues them globally.
		return;
	}
	wp_enqueue_style( 'bwing' );
	wp_enqueue_script( 'bwing' );
} );

/**
 * Template helper: render a template part from the theme (bwing/ folder) or the plugin.
 *
 * @param string $name Template name without extension.
 * @param array  $args Variables.
 */
function bwing_template( $name, $args = array() ) {
	$file = locate_template( 'bwing/' . $name . '.php' );
	if ( ! $file ) {
		$file = BWING_DIR . 'templates/' . $name . '.php';
	}
	if ( file_exists( $file ) ) {
		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $file;
	}
}

/**
 * Internal link that works with every permalink setting (pretty, /index.php/ and plain).
 *
 * @param string $path e.g. "themes/astra" or "/theme-detector/".
 */
function bwing_url( $path = '' ) {
	global $wp_rewrite;
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return home_url( '/' );
	}
	if ( ! get_option( 'permalink_structure' ) ) {
		if ( preg_match( '#^(themes|plugins|premium-themes|premium-plugins|authors)/([^/]+)$#', $path, $m ) ) {
			$types = array( 'themes' => 'theme', 'plugins' => 'plugin', 'premium-themes' => 'premium-theme', 'premium-plugins' => 'premium-plugin', 'authors' => 'author' );
			return add_query_arg( array( 'bwing_type' => $types[ $m[1] ], 'bwing_slug' => $m[2] ), home_url( '/' ) );
		}
		$page = get_page_by_path( $path );
		return $page ? get_permalink( $page ) : home_url( '/' );
	}
	if ( $wp_rewrite && $wp_rewrite->using_index_permalinks() ) {
		$path = $wp_rewrite->index . '/' . $path;
	}
	return home_url( user_trailingslashit( $path ) );
}

/**
 * Public API helpers for themes.
 */
function bwing_get_top( $type = 'theme', $limit = 5 ) {
	return BWing_Data::top_items( $type, $limit );
}
function bwing_get_top_authors( $type = 'all', $limit = 5 ) {
	return BWing_Data::authors( array( 'type' => $type, 'per_page' => $limit ) );
}
function bwing_enqueue() {
	wp_enqueue_style( 'bwing' );
	wp_enqueue_script( 'bwing' );
}
